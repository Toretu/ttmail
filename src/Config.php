<?php
declare(strict_types=1);

namespace Mailings;

final class Config
{
    private static bool $loaded = false;

    // Reads the project's .env file without overriding real environment variables (Docker, Apache SetEnv).
    private static function load(): void
    {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;
        $file = dirname(__DIR__) . '/.env';
        if (!is_readable($file)) {
            return;
        }
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[-1] === $value[0]) {
                $value = substr($value, 1, -1);
            }
            if ($key !== '' && getenv($key) === false) {
                putenv("{$key}={$value}");
            }
        }
    }

    public static function required(string $key): string
    {
        self::load();
        $value = getenv($key);
        if ($value === false || $value === '') {
            throw new \RuntimeException("Missing required configuration value: {$key}");
        }
        return $value;
    }

    public static function get(string $key, string $default): string
    {
        self::load();
        $value = getenv($key);
        return ($value === false || $value === '') ? $default : $value;
    }
}
