<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

try {
    $applied = (new Mailings\Migrator(__DIR__ . '/../migrations'))->run();
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}

echo $applied === [] ? 'Database is up to date.' . PHP_EOL : 'Applied: ' . implode(', ', $applied) . PHP_EOL;
