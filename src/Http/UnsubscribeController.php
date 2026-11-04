<?php
declare(strict_types=1);

namespace Mailings\Http;

use Mailings\Database;

/** Public unsubscribe page. GET only shows a confirmation (mail scanners follow links); POST performs it. */
final class UnsubscribeController
{
    public function show(): never
    {
        $contact = $this->find($_GET['t'] ?? '');
        if ($contact === null) {
            self::page(404, 'Link not valid', '<p>This unsubscribe link is not valid.</p>');
        }
        if ($contact['unsubscribed_at'] !== null) {
            self::page(200, 'Already unsubscribed', '<p>' . self::e($contact['email']) . ' is already unsubscribed. You will not receive more email from us.</p>');
        }
        $token = self::e((string) $_GET['t']);
        self::page(200, 'Unsubscribe', '<p>Do you want to stop receiving email from us at <strong>' . self::e($contact['email']) . '</strong>?</p>'
            . '<p>Vil du ikke lenger motta e-post fra oss?</p>'
            . "<form method=\"post\" action=\"/unsubscribe\"><input type=\"hidden\" name=\"t\" value=\"{$token}\"><button>Unsubscribe / Meld av</button></form>");
    }

    public function confirm(): never
    {
        $contact = $this->find($_POST['t'] ?? '');
        if ($contact === null) {
            self::page(404, 'Link not valid', '<p>This unsubscribe link is not valid.</p>');
        }
        Database::connect()
            ->prepare("UPDATE contacts SET consent_status = 'withdrawn', unsubscribed_at = COALESCE(unsubscribed_at, CURRENT_TIMESTAMP) WHERE id = ?")
            ->execute([$contact['id']]);
        self::page(200, 'Unsubscribed', '<p>Done. You will not receive more email from us. / Du er nå avmeldt.</p>');
    }

    /** @return array<string,mixed>|null */
    private function find(mixed $token): ?array
    {
        if (!is_string($token) || preg_match('/^[0-9a-f]{32}$/', $token) !== 1) {
            return null;
        }
        $stmt = Database::connect()->prepare('SELECT c.id, c.email, c.unsubscribed_at FROM contact_tokens t JOIN contacts c ON c.id = t.contact_id WHERE t.token = ?');
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    private static function e(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function page(int $status, string $title, string $body): never
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex');
        echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . self::e($title) . '</title>'
            . '<body style="font:16px system-ui;max-width:560px;margin:60px auto;padding:0 20px">'
            . '<h1>' . self::e($title) . '</h1>' . $body
            . '<style>button{font:inherit;padding:8px 16px;border:0;border-radius:6px;background:#5b3fd1;color:#fff;cursor:pointer}</style></body>';
        exit;
    }
}
