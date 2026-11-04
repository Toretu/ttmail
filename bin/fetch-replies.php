<?php
declare(strict_types=1);

// Run every few minutes from cron. Reads the inbox of each active mailbox and stores replies from known contacts.
require __DIR__ . '/../src/bootstrap.php';

try {
    $r = (new Mailings\Mail\Replies())->run();
} catch (Throwable $e) {
    fwrite(STDERR, date('c') . ' ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
if ($r['error'] !== null) {
    fwrite(STDERR, date('c') . ' ' . $r['error'] . PHP_EOL);
    exit(1);
}
if ($r['stored'] > 0) {
    echo date('c') . " stored={$r['stored']} seen={$r['seen']} unknown_senders={$r['unknown']}" . PHP_EOL;
}
