<?php
declare(strict_types=1);

namespace Mailings\Auth;

use Mailings\Config;
use Mailings\Database;
use Mailings\Http\HttpException;

/** Server-side session, current user, and the role policy for API routes. */
final class Auth
{
    private const IDLE_SECONDS = 8 * 3600;
    private const RANK = ['viewer' => 1, 'editor' => 2, 'admin' => 3];

    /** @var array<string,mixed>|null */
    private static ?array $user = null;
    private static bool $loaded = false;

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.gc_maxlifetime', (string) self::IDLE_SECONDS);
        session_name('mailings_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => str_starts_with(Config::get('APP_URL', ''), 'https://'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    /** @return array<string,mixed>|null id, email, display_name, role */
    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$user;
        }
        self::$loaded = true;
        self::start();

        $id = $_SESSION['user_id'] ?? null;
        $seen = $_SESSION['last_seen'] ?? 0;
        if (!is_int($id) || time() - (int) $seen > self::IDLE_SECONDS) {
            return self::$user = null;
        }
        // Re-read on every request so a disabled user or changed role takes effect immediately.
        $stmt = Database::connect()->prepare('SELECT id, email, display_name, role FROM users WHERE id = ? AND is_active = 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row === false) {
            self::logout();
            return self::$user = null;
        }
        $_SESSION['last_seen'] = time();
        return self::$user = $row;
    }

    public static function login(int $userId): void
    {
        self::start();
        session_regenerate_id(true);
        $_SESSION = ['user_id' => $userId, 'last_seen' => time()];
        Database::connect()->prepare('UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$userId]);
        self::$loaded = false;
        self::$user = null;
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        setcookie('mailings_session', '', ['expires' => 1, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
        self::$user = null;
        self::$loaded = true;
    }

    /** Minimum role needed for an API request, or null if it is public. */
    public static function requiredRole(string $method, string $path): ?string
    {
        if (in_array($path, ['/api/health', '/api/auth/config'], true)) {
            return null;
        }
        if ($path === '/api/me') {
            return 'viewer';
        }
        if (str_starts_with($path, '/api/settings') || str_starts_with($path, '/api/users')) {
            return 'admin';
        }
        if (str_starts_with($path, '/api/mailboxes') && $method !== 'GET') {
            return 'admin';
        }
        return $method === 'GET' ? 'viewer' : 'editor';
    }

    /** Enforces authentication, role and same-origin for state-changing requests. */
    public static function enforce(string $method, string $path): void
    {
        if (!str_starts_with($path, '/api/')) {
            return;
        }
        $role = self::requiredRole($method, $path);
        if ($role === null) {
            return;
        }
        $user = self::user();
        if ($user === null) {
            throw new HttpException(401, 'Sign in required');
        }
        if ($method !== 'GET' && !self::sameOrigin()) {
            throw new HttpException(403, 'Cross-origin request refused');
        }
        if (self::RANK[$user['role']] < self::RANK[$role]) {
            throw new HttpException(403, "This action requires the {$role} role");
        }
    }

    public static function sameOrigin(): bool
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
        if ($origin === null) {
            return true;
        }
        $originHost = parse_url($origin, PHP_URL_HOST);
        $originPort = parse_url($origin, PHP_URL_PORT);
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $expected = $originPort === null ? (string) $originHost : $originHost . ':' . $originPort;
        return $originHost !== null && hash_equals(strtolower($host), strtolower($expected));
    }

    /** Only same-site relative paths are accepted as a post-login destination. */
    public static function safeNext(?string $next): string
    {
        if ($next !== null && preg_match('#^/(?!/)[A-Za-z0-9_./?=&%#-]*$#', $next) === 1 && !str_starts_with($next, '/auth/')) {
            return $next;
        }
        return '/app/';
    }
}
