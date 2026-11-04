<?php
declare(strict_types=1);

namespace Mailings\Mail;

use Mailings\Config;
use Mailings\Database;

/** Per-contact unsubscribe tokens. A token only allows unsubscribing that one contact. */
final class Unsubscribe
{
    public static function tokenFor(int $contactId): string
    {
        $pdo = Database::connect();
        $pdo->prepare('INSERT IGNORE INTO contact_tokens (contact_id, token) VALUES (?, ?)')
            ->execute([$contactId, bin2hex(random_bytes(16))]);
        $stmt = $pdo->prepare('SELECT token FROM contact_tokens WHERE contact_id = ?');
        $stmt->execute([$contactId]);
        return (string) $stmt->fetchColumn();
    }

    public static function url(string $token): string
    {
        return rtrim(Config::required('APP_URL'), '/') . '/unsubscribe?t=' . $token;
    }
}
