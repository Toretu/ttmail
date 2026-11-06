<?php
declare(strict_types=1);

namespace Mailings\Mail;

use Mailings\Config;
use Mailings\Database;

/**
 * Personal details links ({{details_link}}): one token per campaign and contact, valid for a limited time.
 * Kept apart from the unsubscribe token so a forwarded unsubscribe link never opens the details page.
 */
final class DetailsLink
{
    public const SAMPLE_TOKEN = '00000000000000000000000000000000';

    /** Days a link stays valid after it is created (REPLY_LINK_DAYS, 1–365). */
    public static function days(): int
    {
        return min(365, max(1, (int) Config::get('REPLY_LINK_DAYS', '90')));
    }

    public static function tokenFor(int $campaignId, int $contactId): string
    {
        $pdo = Database::connect();
        $pdo->prepare('INSERT IGNORE INTO campaign_links (campaign_id, contact_id, token, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? DAY))')
            ->execute([$campaignId, $contactId, bin2hex(random_bytes(16)), self::days()]);
        $stmt = $pdo->prepare('SELECT token FROM campaign_links WHERE campaign_id = ? AND contact_id = ?');
        $stmt->execute([$campaignId, $contactId]);
        return (string) $stmt->fetchColumn();
    }

    public static function url(string $token, int $campaignId): string
    {
        return rtrim(Config::required('APP_URL'), '/') . '/reply?t=' . $token . '&c=' . $campaignId;
    }

    /** @return array{contact_id:int, expired:bool}|null */
    public static function resolve(string $token, int $campaignId): ?array
    {
        $stmt = Database::connect()->prepare('SELECT contact_id, expires_at < NOW() AS expired FROM campaign_links WHERE token = ? AND campaign_id = ?');
        $stmt->execute([$token, $campaignId]);
        $row = $stmt->fetch();
        return $row === false ? null : ['contact_id' => (int) $row['contact_id'], 'expired' => (bool) $row['expired']];
    }
}
