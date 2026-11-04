<?php
declare(strict_types=1);

namespace Mailings\Http;

use Mailings\Database;
use Mailings\Validator;

/**
 * Public page opened from the personal link in a campaign mail: the contact can correct their details and
 * pick products. Answers are stored for an editor to review; master data is never changed from here.
 */
final class ReplyController
{
    public const COMPANY_FIELDS = [
        'org_number' => 'Organisation number / Org.nr.',
        'billing_address' => 'Billing address / Fakturaadresse',
        'postal_code' => 'Postal code / Postnummer',
        'city' => 'City / Sted',
        'website' => 'Website / Nettside',
    ];
    public const CONTACT_FIELDS = [
        'first_name' => 'First name / Fornavn',
        'last_name' => 'Last name / Etternavn',
        'job_title' => 'Role / Stilling',
        'phone' => 'Phone / Telefon',
    ];

    public function show(): never
    {
        $ctx = $this->context($_GET['t'] ?? '', $_GET['c'] ?? '');
        $this->form($ctx, [], [], '', null);
    }

    public function submit(): never
    {
        $ctx = $this->context($_POST['t'] ?? '', $_POST['c'] ?? '');
        $posted = ['company' => [], 'contact' => []];
        foreach (['company' => self::COMPANY_FIELDS, 'contact' => self::CONTACT_FIELDS] as $group => $fields) {
            foreach ($fields as $field => $label) {
                $value = $_POST[$group . '_' . $field] ?? '';
                $posted[$group][$field] = is_string($value) ? $value : '';
            }
        }
        $comment = is_string($_POST['comment'] ?? null) ? trim($_POST['comment']) : '';
        $wanted = is_array($_POST['products'] ?? null) ? array_map('strval', $_POST['products']) : [];
        $productIds = [];
        foreach ($ctx['products'] as $product) {
            if (in_array((string) $product['id'], $wanted, true)) {
                $productIds[] = (int) $product['id'];
            }
        }

        try {
            $changes = [
                'company' => $this->diff($posted['company'], $ctx['company'], array_intersect_key(\Mailings\Schema::companyRules(), self::COMPANY_FIELDS)),
                'contact' => $this->diff($posted['contact'], $ctx['contact'], array_intersect_key(\Mailings\Schema::contactRules(), self::CONTACT_FIELDS)),
            ];
            if (mb_strlen($comment) > 2000) {
                throw new HttpException(422, 'Validation failed', ['comment' => 'must be at most 2000 characters']);
            }
        } catch (HttpException $e) {
            $this->form($ctx, $posted, $wanted, $comment, implode('; ', array_map(static fn ($f, $m): string => "{$f} {$m}", array_keys($e->errors), $e->errors)));
        }

        $changes = array_filter($changes);
        if ($changes === [] && $productIds === [] && $comment === '') {
            $this->form($ctx, $posted, $wanted, $comment, 'You have not changed or chosen anything. / Du har ikke endret eller valgt noe.');
        }

        Database::connect()->prepare(
            "INSERT INTO campaign_responses (campaign_id, contact_id, changes, product_ids, comment, status) VALUES (?, ?, ?, ?, ?, 'new')
             ON DUPLICATE KEY UPDATE changes = VALUES(changes), product_ids = VALUES(product_ids), comment = VALUES(comment), status = 'new'"
        )->execute([
            $ctx['campaign']['id'],
            $ctx['contact']['id'],
            $changes === [] ? null : json_encode($changes, JSON_UNESCAPED_UNICODE),
            $productIds === [] ? null : json_encode($productIds),
            $comment === '' ? null : $comment,
        ]);
        self::page(200, 'Thank you / Takk', '<p>We have received your answer and will get back to you.</p><p>Vi har mottatt svaret ditt og tar kontakt.</p>');
    }

    /**
     * @param array<string,string> $posted
     * @param array<string,mixed> $current
     * @param array<string,array<string,mixed>> $rules
     * @return array<string,string|null> only fields that differ from the current value
     */
    private function diff(array $posted, array $current, array $rules): array
    {
        $clean = Validator::clean($posted, $rules, true);
        $out = [];
        foreach ($clean as $field => $value) {
            $old = $current[$field] ?? null;
            if (($old === '' ? null : $old) !== $value) {
                $out[$field] = $value;
            }
        }
        return $out;
    }

    /** @return array<string,mixed> */
    private function context(mixed $token, mixed $campaignId): array
    {
        $invalid = static fn () => self::page(404, 'Link not valid', '<p>This link is not valid or has expired. / Lenken er ugyldig eller utløpt.</p>');
        if (!is_string($token) || preg_match('/^[0-9a-f]{32}$/', $token) !== 1 || !is_string($campaignId) || !ctype_digit($campaignId)) {
            $invalid();
        }
        $pdo = Database::connect();
        $stmt = $pdo->prepare(
            "SELECT c.*, r.campaign_id FROM contact_tokens t
             JOIN contacts c ON c.id = t.contact_id
             JOIN campaign_recipients r ON r.contact_id = c.id AND r.campaign_id = ? AND r.status = 'sent'
             WHERE t.token = ?"
        );
        $stmt->execute([(int) $campaignId, $token]);
        $contact = $stmt->fetch();
        if ($contact === false || $contact['unsubscribed_at'] !== null) {
            $invalid();
        }
        $campaign = $pdo->prepare('SELECT id, name, status FROM campaigns WHERE id = ?');
        $campaign->execute([(int) $campaignId]);
        $campaign = $campaign->fetch();
        $company = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $company->execute([$contact['company_id']]);
        $products = $pdo->prepare('SELECT id, name, description, price FROM campaign_products WHERE campaign_id = ? ORDER BY sort_order, id');
        $products->execute([(int) $campaignId]);

        return [
            'token' => $token, 'campaign' => $campaign, 'contact' => $contact,
            'company' => $company->fetch(), 'products' => $products->fetchAll(),
            'closed' => $campaign['status'] === 'closed',
        ];
    }

    /**
     * @param array<string,mixed> $ctx
     * @param array<string,array<string,string>> $posted
     * @param list<string> $wanted
     */
    private function form(array $ctx, array $posted, array $wanted, string $comment, ?string $error): never
    {
        if ($ctx['closed']) {
            self::page(200, (string) $ctx['campaign']['name'], '<p>This campaign is closed. / Denne kampanjen er avsluttet.</p>');
        }
        $e = self::e(...);
        $existing = Database::connect()->prepare('SELECT product_ids, comment FROM campaign_responses WHERE campaign_id = ? AND contact_id = ?');
        $existing->execute([$ctx['campaign']['id'], $ctx['contact']['id']]);
        $prev = $existing->fetch();
        if ($posted === [] && $prev !== false) {
            $wanted = array_map('strval', json_decode((string) $prev['product_ids'], true) ?: []);
            $comment = (string) $prev['comment'];
        }

        $html = $error !== null ? '<p class="err">' . $e($error) . '</p>' : '';
        $html .= '<p>Check that your details are correct and correct anything that is wrong. / Sjekk at opplysningene stemmer, og rett opp det som er feil.</p>';
        $html .= '<form method="post" action="/reply"><input type="hidden" name="t" value="' . $e($ctx['token']) . '"><input type="hidden" name="c" value="' . (int) $ctx['campaign']['id'] . '">';
        $html .= '<h2>' . $e((string) $ctx['company']['name']) . '</h2>';
        foreach (['company' => self::COMPANY_FIELDS, 'contact' => self::CONTACT_FIELDS] as $group => $fields) {
            if ($group === 'contact') {
                $html .= '<h2>Contact person / Kontaktperson</h2><p class="hint">' . $e((string) $ctx['contact']['email']) . '</p>';
            }
            foreach ($fields as $field => $label) {
                $value = $posted[$group][$field] ?? (string) ($ctx[$group][$field] ?? '');
                $html .= '<label>' . $e($label) . '<input name="' . $group . '_' . $field . '" value="' . $e($value) . '" maxlength="255"></label>';
            }
        }
        if ($ctx['products'] !== []) {
            $html .= '<h2>Choose what you are interested in / Velg det du er interessert i</h2>';
            foreach ($ctx['products'] as $p) {
                $checked = in_array((string) $p['id'], $wanted, true) ? ' checked' : '';
                $desc = $p['description'] !== null && $p['description'] !== '' ? ' <span class="hint">' . $e((string) $p['description']) . '</span>' : '';
                $html .= '<label class="check"><input type="checkbox" name="products[]" value="' . (int) $p['id'] . '"' . $checked . '> '
                    . $e((string) $p['name']) . ' – kr ' . number_format((float) $p['price'], 0, ',', ' ') . $desc . '</label>';
            }
            $html .= '<p class="hint">This is not a binding order. We will contact you. / Dette er ikke en bindende bestilling. Vi tar kontakt.</p>';
        }
        $html .= '<label>Message / Melding<textarea name="comment" rows="4" maxlength="2000">' . $e($comment) . '</textarea></label>';
        $html .= '<button>Send / Send inn</button></form>';
        self::page(200, (string) $ctx['campaign']['name'], $html);
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
        header('Referrer-Policy: no-referrer');
        header('X-Robots-Tag: noindex');
        echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . self::e($title) . '</title>'
            . '<body style="font:16px system-ui;max-width:560px;margin:40px auto;padding:0 20px">'
            . '<h1>' . self::e($title) . '</h1>' . $body
            . '<style>label{display:block;margin:10px 0}label input:not([type=checkbox]),textarea{display:block;width:100%;box-sizing:border-box;font:inherit;padding:8px;margin-top:4px}'
            . '.check{display:flex;gap:8px;align-items:center}.hint{color:#666;font-size:14px}.err{color:#b00020;font-weight:600}h2{font-size:18px;margin-top:28px}'
            . 'button{font:inherit;padding:8px 16px;border:0;border-radius:6px;background:#5b3fd1;color:#fff;cursor:pointer;margin-top:12px}</style></body>';
        exit;
    }
}
