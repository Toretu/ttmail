<?php
declare(strict_types=1);

namespace Mailings;

use PDO;

final class Database
{
    private static ?PDO $pdo = null;

    public static function connect(bool $multiStatement = false): PDO
    {
        if (!$multiStatement && self::$pdo !== null) {
            return self::$pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            Config::required('DB_HOST'),
            Config::get('DB_PORT', '3306'),
            Config::required('DB_NAME')
        );
        $pdo = new PDO($dsn, Config::required('DB_USER'), Config::required('DB_PASSWORD'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => $multiStatement,
        ]);

        if (!$multiStatement) {
            self::$pdo = $pdo;
        }
        return $pdo;
    }
}
