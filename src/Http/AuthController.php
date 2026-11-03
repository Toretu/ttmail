<?php
declare(strict_types=1);

namespace Mailings\Http;

use Mailings\Auth\Auth;
use Mailings\Config;
use Mailings\Database;
use Mailings\Graph\GraphClient;
use Mailings\Graph\GraphException;

/** Sign-in with Microsoft 365 (OpenID Connect authorization code flow with PKCE) and a development-only login. */
final class AuthController
{
    /** @return array<string,mixed> */
    public function config(): array
    {
        return ['data' => ['microsoft' => GraphClient::status()['ready'], 'dev_login' => self::devLoginEnabled()]];
    }

    /** @return array<string,mixed> */
    public function me(): array
    {
        return ['data' => Auth::user()];
    }

    public function login(): never
    {
        if (!GraphClient::status()['ready']) {
            self::fail('Microsoft sign-in is not configured on the server.');
        }
        Auth::start();
        $verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
        $_SESSION['oidc'] = [
            'state' => bin2hex(random_bytes(16)),
            'nonce' => bin2hex(random_bytes(16)),
            'verifier' => $verifier,
            'next' => Auth::safeNext(is_string($_GET['next'] ?? null) ? $_GET['next'] : null),
            'at' => time(),
        ];
        $query = http_build_query([
            'client_id' => Config::required('GRAPH_CLIENT_ID'),
            'response_type' => 'code',
            'redirect_uri' => self::redirectUri(),
            'response_mode' => 'query',
            'scope' => 'openid profile email',
            'state' => $_SESSION['oidc']['state'],
            'nonce' => $_SESSION['oidc']['nonce'],
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
            'prompt' => 'select_account',
        ]);
        header('Location: https://login.microsoftonline.com/' . rawurlencode(Config::required('GRAPH_TENANT_ID')) . '/oauth2/v2.0/authorize?' . $query);
        exit;
    }

    public function callback(): never
    {
        Auth::start();
        $flow = $_SESSION['oidc'] ?? null;
        unset($_SESSION['oidc']);

        if (!is_array($flow) || time() - (int) $flow['at'] > 600) {
            self::fail('The sign-in session expired. Please try again.');
        }
        if (isset($_GET['error'])) {
            self::fail('Microsoft did not complete the sign-in (' . (is_string($_GET['error']) ? $_GET['error'] : 'error') . ').');
        }
        $state = $_GET['state'] ?? null;
        $code = $_GET['code'] ?? null;
        if (!is_string($state) || !is_string($code) || !hash_equals($flow['state'], $state)) {
            self::fail('The sign-in response was not valid. Please try again.');
        }

        $client = new GraphClient();
        $response = $client->request(
            'POST',
            'https://login.microsoftonline.com/' . rawurlencode(Config::required('GRAPH_TENANT_ID')) . '/oauth2/v2.0/token',
            [],
            http_build_query([
                'client_id' => Config::required('GRAPH_CLIENT_ID'),
                'client_secret' => Config::required('GRAPH_CLIENT_SECRET'),
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => self::redirectUri(),
                'code_verifier' => $flow['verifier'],
            ])
        );
        if (!is_string($response['id_token'] ?? null)) {
            error_log('Sign-in token exchange failed: ' . $client->describe($response, 'unknown error'));
            self::fail('Microsoft rejected the sign-in. Check the app registration redirect URI and client secret.');
        }

        // The ID token comes straight from Microsoft's token endpoint over TLS, so its claims are checked
        // here instead of verifying the signature (OpenID Connect Core 3.1.3.7).
        $claims = self::claims($response['id_token']);
        $tenant = Config::required('GRAPH_TENANT_ID');
        $tenantIsGuid = preg_match('/^[0-9a-f-]{36}$/i', $tenant) === 1;
        $valid = is_array($claims)
            && ($claims['aud'] ?? null) === Config::required('GRAPH_CLIENT_ID')
            && hash_equals($flow['nonce'], (string) ($claims['nonce'] ?? ''))
            && (int) ($claims['exp'] ?? 0) > time()
            && is_string($claims['tid'] ?? null)
            && ($claims['iss'] ?? null) === 'https://login.microsoftonline.com/' . $claims['tid'] . '/v2.0'
            && (!$tenantIsGuid || strtolower($claims['tid']) === strtolower($tenant));
        if (!$valid) {
            self::fail('The sign-in response could not be verified.');
        }

        $email = strtolower(trim((string) ($claims['preferred_username'] ?? $claims['email'] ?? '')));
        $userId = $email === '' ? null : self::findOrBootstrapUser($email, (string) ($claims['name'] ?? ''));
        if ($userId === null) {
            self::fail(($email === '' ? 'Your account' : $email) . ' is not authorised to use this application. Ask an administrator to add you.');
        }

        Auth::login($userId);
        header('Location: ' . $flow['next']);
        exit;
    }

    /** POST /auth/dev-login — only when APP_ENV=development and AUTH_DEV_LOGIN=1. Optional "email" selects an existing active user. */
    public function devLogin(): array
    {
        if (!self::devLoginEnabled()) {
            throw new HttpException(404, 'Not found');
        }
        $body = json_decode((string) file_get_contents('php://input'), true);
        $email = is_array($body) && is_string($body['email'] ?? null) ? strtolower(trim($body['email'])) : '';

        $pdo = Database::connect();
        if ($email !== '') {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND is_active = 1');
            $stmt->execute([$email]);
            $id = $stmt->fetchColumn();
            if ($id === false) {
                throw new HttpException(404, 'No active user with that email');
            }
            $userId = (int) $id;
        } else {
            $admin = strtolower(Config::get('AUTH_BOOTSTRAP_ADMIN', 'dev@localhost'));
            $userId = self::ensureAdmin($admin, 'Local developer');
        }
        Auth::login($userId);
        return ['data' => ['ok' => true]];
    }

    /** @return array<string,mixed> */
    public function logout(): array
    {
        if (!Auth::sameOrigin()) {
            throw new HttpException(403, 'Cross-origin request refused');
        }
        Auth::logout();
        return ['data' => ['ok' => true]];
    }

    private static function devLoginEnabled(): bool
    {
        return Config::get('APP_ENV', 'production') === 'development' && Config::get('AUTH_DEV_LOGIN', '0') === '1';
    }

    private static function redirectUri(): string
    {
        return rtrim(Config::required('APP_URL'), '/') . '/auth/callback';
    }

    /** @return array<string,mixed>|null */
    private static function claims(string $jwt): ?array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return null;
        }
        $payload = json_decode((string) base64_decode(strtr($parts[1], '-_', '+/'), false), true);
        return is_array($payload) ? $payload : null;
    }

    /** Existing active user, or — if no admin exists yet — the configured bootstrap admin. */
    private static function findOrBootstrapUser(string $email, string $name): ?int
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare('SELECT id, is_active FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        if ($row !== false) {
            return (int) $row['is_active'] === 1 ? (int) $row['id'] : null;
        }
        $bootstrap = strtolower(Config::get('AUTH_BOOTSTRAP_ADMIN', ''));
        $admins = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1")->fetchColumn();
        if ($bootstrap !== '' && $bootstrap === $email && $admins === 0) {
            return self::ensureAdmin($email, $name);
        }
        return null;
    }

    private static function ensureAdmin(string $email, string $name): int
    {
        $pdo = Database::connect();
        $pdo->prepare("INSERT INTO users (email, display_name, role, is_active) VALUES (?, ?, 'admin', 1)
            ON DUPLICATE KEY UPDATE role = 'admin', is_active = 1")->execute([$email, $name !== '' ? $name : null]);
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        return (int) $stmt->fetchColumn();
    }

    private static function fail(string $message): never
    {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        $text = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo '<!doctype html><meta charset="utf-8"><title>Sign-in failed</title>'
            . '<body style="font:16px system-ui;max-width:560px;margin:60px auto;padding:0 20px">'
            . "<h1>Sign-in failed</h1><p>{$text}</p><p><a href=\"/login.html\">Back to sign-in</a></p></body>";
        exit;
    }
}
