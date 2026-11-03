<?php
declare(strict_types=1);

namespace Mailings;

final class Config
{
    public static function required(string $key): string
    {
        $value = getenv($key);
        if ($value === false || $value === '') {
            throw new \RuntimeException("Missing required configuration value: {$key}");
        }
        return $value;
    }

    public static function get(string $key, string $default): string
    {
        $value = getenv($key);
        return ($value === false || $value === '') ? $default : $value;
    }
}
