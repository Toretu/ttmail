<?php
declare(strict_types=1);

// Run every minute from cron. Sends up to MAIL_RATE_PER_MINUTE messages for approved campaigns.
require __DIR__ . '/../src/bootstrap.php';

try {
    $r = (new Mailings\Mail\Queue())->run();
} catch (Throwable $e) {
    fwrite(STDERR, date('c') . ' ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
if (array_sum($r) > 0) {
    echo date('c') . " sent={$r['sent']} failed={$r['failed']} skipped={$r['skipped']} paused={$r['paused']}" . PHP_EOL;
}
