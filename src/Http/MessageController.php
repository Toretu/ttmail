<?php
declare(strict_types=1);

namespace Mailings\Http;

use Mailings\Auth\Auth;
use Mailings\Database;
use Mailings\Mail\Composer;
use Mailings\Mail\Dispatch;
use Mailings\Mail\Template;

/** The campaign's message (sender mailbox, subject, body), preview, and test send. */
final class MessageController
{
    public function __construct(private readonly CrudController $campaigns)
    {
    }

    /** @param array<string,string> $params @return array<string,mixed> */
    public function get(array $params): array
    {
        $c = $this->campaigns->find((int) $params['id']);
        return ['data' => [
            'mailbox_id' => $c['mailbox_id'] === null ? null : (int) $c['mailbox_id'],
            'subject' => $c['subject'] ?? '',
            'body' => $c['body'] ?? '',
            'locked' => $c['send_state'] !== 'draft' || $this->sentCount((int) $c['id']) > 0,
            'placeholders' => Template::PLACEHOLDERS,
        ]];
    }

    /** @param array<string,string> $params @return array<string,mixed> */
    public function save(array $params): array
    {
        $id = (int) $params['id'];
        $this->campaigns->find($id);
        $campaign = $this->campaigns->find($id);
        if ($campaign['send_state'] !== 'draft' || $this->sentCount($id) > 0) {
            throw new HttpException(409, 'Sending has been approved or started for this campaign, so the message can no longer be changed.');
        }
        $body = Json::body();
        $errors = [];
        $subject = is_string($body['subject'] ?? null) ? trim($body['subject']) : '';
        $text = is_string($body['body'] ?? null) ? $body['body'] : '';
        if (mb_strlen($subject) > 255) {
            $errors['subject'] = 'must be at most 255 characters';
        }
        if (mb_strlen($text) > 20000) {
            $errors['body'] = 'must be at most 20000 characters';
        }
        foreach (['subject' => $subject, 'body' => $text] as $field => $value) {
            $unknown = Template::unknown($value);
            if ($unknown !== []) {
                $errors[$field] = 'unknown placeholder: ' . implode(', ', array_map(static fn (string $n): string => '{{' . $n . '}}', $unknown));
            }
        }
        $mailboxId = $body['mailbox_id'] ?? null;
        if ($mailboxId === '' || $mailboxId === null) {
            $mailboxId = null;
        } else {
            $stmt = Database::connect()->prepare('SELECT id FROM mailboxes WHERE id = ? AND is_active = 1');
            $stmt->execute([(int) $mailboxId]);
            if ($stmt->fetchColumn() === false) {
                $errors['mailbox_id'] = 'must be an active mailbox';
            }
            $mailboxId = (int) $mailboxId;
        }
        if ($errors !== []) {
            throw new HttpException(422, 'Validation failed', $errors);
        }
        Database::connect()->prepare('UPDATE campaigns SET mailbox_id = ?, subject = ?, body = ?, test_sent_at = NULL WHERE id = ?')
            ->execute([$mailboxId, $subject === '' ? null : $subject, $text === '' ? null : $text, $id]);
        return $this->get($params);
    }

    /**
     * Renders the message for a recipient (contact_id) or a sample contact. Nothing is sent.
     *
     * @param array<string,string> $params
     * @return array<string,mixed>
     */
    public function preview(array $params): array
    {
        $campaign = $this->campaigns->find((int) $params['id']);
        $this->assertComplete($campaign);
        $body = $_SERVER['CONTENT_LENGTH'] ?? '0';
        $contact = $body !== '0' ? (Json::body()['contact_id'] ?? null) : null;
        [$subject, $html] = $this->render($campaign, $contact === null ? null : (int) $contact);
        return ['data' => ['subject' => $subject, 'html' => $html, 'from' => $this->mailbox($campaign)['email']]];
    }

    /**
     * Sends the message once to the given address (default: the signed-in user) with a [TEST] subject prefix.
     *
     * @param array<string,string> $params
     * @return array<string,mixed>
     */
    public function test(array $params): array
    {
        $campaign = $this->campaigns->find((int) $params['id']);
        $this->assertComplete($campaign);
        $body = Json::body();
        $to = is_string($body['to'] ?? null) ? trim($body['to']) : '';
        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            throw new HttpException(422, 'Validation failed', ['to' => 'must be a valid email address']);
        }
        $mailbox = $this->mailbox($campaign);
        [$subject, $html] = $this->render($campaign, null);
        try {
            Dispatch::send($mailbox['email'], $to, '[TEST] ' . $subject, $html);
        } catch (\Mailings\Graph\GraphException $e) {
            throw new HttpException(502, $e->getMessage());
        }
        Database::connect()->prepare('UPDATE campaigns SET test_sent_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$campaign['id']]);
        error_log(sprintf('Test mail for campaign %d sent from %s to %s by %s', $campaign['id'], $mailbox['email'], $to, Auth::user()['email'] ?? '?'));
        return ['data' => ['sent_to' => $to, 'from' => $mailbox['email'], 'dry_run' => Dispatch::dryRun()]];
    }

    /** @param array<string,mixed> $campaign */
    private function assertComplete(array $campaign): void
    {
        $missing = [];
        if ($campaign['mailbox_id'] === null) {
            $missing['mailbox_id'] = 'choose a sender mailbox';
        }
        if (trim((string) $campaign['subject']) === '') {
            $missing['subject'] = 'is required';
        }
        if (trim((string) $campaign['body']) === '') {
            $missing['body'] = 'is required';
        }
        if ($missing !== []) {
            throw new HttpException(422, 'Save the sender, subject and body first', $missing);
        }
    }

    /** @param array<string,mixed> $campaign @return array<string,mixed> */
    private function mailbox(array $campaign): array
    {
        $stmt = Database::connect()->prepare('SELECT * FROM mailboxes WHERE id = ?');
        $stmt->execute([$campaign['mailbox_id']]);
        $mailbox = $stmt->fetch();
        if ($mailbox === false || !(int) $mailbox['is_active']) {
            throw new HttpException(409, 'The selected sender mailbox is missing or inactive.');
        }
        return $mailbox;
    }

    /** @param array<string,mixed> $campaign @return array{string,string} */
    private function render(array $campaign, ?int $contactId): array
    {
        return Composer::build($campaign, $this->mailbox($campaign), $contactId);
    }

    private function sentCount(int $campaignId): int
    {
        $stmt = Database::connect()->prepare("SELECT COUNT(*) FROM campaign_recipients WHERE campaign_id = ? AND status <> 'pending'");
        $stmt->execute([$campaignId]);
        return (int) $stmt->fetchColumn();
    }
}
