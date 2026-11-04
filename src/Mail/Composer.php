<?php
declare(strict_types=1);

namespace Mailings\Mail;

use Mailings\Config;
use Mailings\Database;
use Mailings\Http\HttpException;

/** Builds the subject and HTML for one campaign message. */
final class Composer
{
    /**
     * @param array<string,mixed> $campaign
     * @param array<string,mixed> $mailbox
     * @return array{string,string} subject, html. Without a contact id, sample data and an inactive link are used.
     */
    public static function build(array $campaign, array $mailbox, ?int $contactId): array
    {
        $vars = ['first_name' => 'Kari', 'last_name' => 'Nordmann', 'full_name' => 'Kari Nordmann', 'company' => 'Eksempel AS', 'campaign' => (string) $campaign['name']];
        $vars['products'] = self::products((int) $campaign['id']);
        $base = rtrim(Config::get('APP_URL', ''), '/');
        $vars['details_link'] = $base . '/reply?t=' . str_repeat('0', 32) . '&c=' . (int) $campaign['id'];
        $link = rtrim(Config::get('APP_URL', ''), '/') . '/unsubscribe?t=' . str_repeat('0', 32);
        if ($contactId !== null) {
            $stmt = Database::connect()->prepare('SELECT c.id, c.first_name, c.last_name, co.name AS company FROM contacts c JOIN companies co ON co.id = c.company_id WHERE c.id = ?');
            $stmt->execute([$contactId]);
            $c = $stmt->fetch();
            if ($c === false) {
                throw new HttpException(404, 'Contact not found');
            }
            $vars = [
                'first_name' => $c['first_name'], 'last_name' => $c['last_name'],
                'full_name' => trim($c['first_name'] . ' ' . $c['last_name']),
                'company' => $c['company'], 'campaign' => (string) $campaign['name'],
            ];
            $vars['details_link'] = $base . '/reply?t=' . Unsubscribe::tokenFor((int) $c['id']) . '&c=' . (int) $campaign['id'];
            $link = Unsubscribe::url(Unsubscribe::tokenFor((int) $c['id']));
        }
        return [
            Template::subject((string) $campaign['subject'], $vars),
            Template::html((string) $campaign['body'], $vars, $link, (string) $mailbox['display_name']),
        ];
    }

    /** One line per product that still has stock (sold and reserved count as taken). Evaluated when each mail is built. */
    private static function products(int $campaignId): string
    {
        $stmt = Database::connect()->prepare(
            "SELECT p.name, p.description, p.price, p.quantity_available,
                COALESCE(SUM(CASE WHEN s.status IN ('sold','reserved') THEN s.quantity END), 0) AS taken
             FROM campaign_products p LEFT JOIN product_sales s ON s.product_id = p.id
             WHERE p.campaign_id = ? GROUP BY p.id, p.name, p.description, p.price, p.quantity_available, p.sort_order
             ORDER BY p.sort_order, p.id"
        );
        $stmt->execute([$campaignId]);
        $lines = [];
        foreach ($stmt->fetchAll() as $p) {
            if ($p['quantity_available'] !== null && (int) $p['taken'] >= (int) $p['quantity_available']) {
                continue;
            }
            $line = $p['name'] . ' – kr ' . number_format((float) $p['price'], 0, ',', ' ');
            if ($p['description'] !== null && $p['description'] !== '') {
                $line .= ' (' . $p['description'] . ')';
            }
            $lines[] = $line;
        }
        return implode("\n", $lines);
    }
}
