<?php
declare(strict_types=1);

namespace Mailings;

final class Migrator
{
    public function __construct(private readonly string $directory)
    {
    }

    /** @return list<string> names of migrations applied by this run */
    public function run(): array
    {
        $pdo = Database::connect(true);
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                name VARCHAR(190) NOT NULL PRIMARY KEY,
                applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $done = $pdo->query('SELECT name FROM schema_migrations')->fetchAll(\PDO::FETCH_COLUMN);
        $files = glob($this->directory . '/*.sql') ?: [];
        sort($files);

        $applied = [];
        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $done, true)) {
                continue;
            }
            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new \RuntimeException("Cannot read migration {$name}");
            }
            try {
                $pdo->exec($sql);
                $stmt = $pdo->prepare('INSERT INTO schema_migrations (name) VALUES (?)');
                $stmt->execute([$name]);
            } catch (\Throwable $e) {
                throw new \RuntimeException("Migration {$name} failed: " . $e->getMessage(), 0, $e);
            }
            $applied[] = $name;
        }
        return $applied;
    }
}
