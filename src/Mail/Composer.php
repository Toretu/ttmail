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
}
