<?php
declare(strict_types=1);

namespace Mailings\Http;

use Mailings\Auth\Auth;
use Mailings\Database;
use Mailings\Mail\Dispatch;
use Mailings\Mail\Queue;

/** Approval and control of campaign sending. The actual sending is done by bin/send-queue.php. */
final class SendController
{
    public function __construct(private readonly CrudController $campaigns)
    {
    }

    /** @param array<string,string> $params @return array<string,mixed> */
    public function status(array $params): array
    {
        return ['data' => $this->snapshot((int) $params['id'])];
    }

    /**
     * Approves sending. Requires a successful test send of the current message and confirm_count equal to the pending recipients.
     *
     * @param array<string,string> $params
     * @return array<string,mixed>
     */
    public function approve(array $params): array
    {
        $id = (int) $params['id'];
        $campaign = $this->campaigns->find($id);
        $pending = $this->counts($id)['pending'];
        $body = Json::body();

        if ($campaign['send_state'] !== 'draft') {
            throw new HttpException(409, 'Sending has already been approved for this campaign.');
        }
        if ($campaign['status'] === 'closed') {
            throw new HttpException(409, 'This campaign is closed.');
        }
        if ($campaign['mailbox_id'] === null || trim((string) $campaign['subject']) === '' || trim((string) $campaign['body']) === '') {
            throw new HttpException(409, 'Save the sender, subject and message first.');
        }
        if ($campaign['test_sent_at'] === null) {
            throw new HttpException(409, 'Send a test mail of the current message first.');
        }
        if ($pending === 0) {
            throw new HttpException(409, 'There are no pending recipients.');
        }
        if (($body['confirm_count'] ?? null) !== $pending) {
            throw new HttpException(422, 'Validation failed', ['confirm_count' => "must be the number of recipients ({$pending})"]);
        }

        $stmt = Database::connect()->prepare(
            "UPDATE campaigns SET send_state = 'sending', approved_by = ?, approved_at = NOW(), pause_reason = NULL WHERE id = ? AND send_state = 'draft'"
        );
        $stmt->execute([Auth::user()['email'] ?? 'unknown', $id]);
        if ($stmt->rowCount() === 0) {
            throw new HttpException(409, 'Sending has already been approved for this campaign.');
        }
        error_log(sprintf('Campaign %d approved for sending to %d recipients by %s', $id, $pending, Auth::user()['email'] ?? '?'));
        return ['data' => $this->snapshot($id)];
    }

    /** @param array<string,string> $params @return array<string,mixed> */
    public function pause(array $params): array
    {
        return $this->transition((int) $params['id'], 'sending', 'paused', 'Paused by ' . (Auth::user()['email'] ?? 'a user') . '.');
    }

    /** @param array<string,string> $params @return array<string,mixed> */
    public function resume(array $params): array
    {
        return $this->transition((int) $params['id'], 'paused', 'sending', null);
    }

    /**
     * Puts failed recipients back in the queue. Recipients with an unknown outcome are left alone: the mail may already
     * have been delivered, so they can only be added again by hand after checking Sent Items.
     *
     * @param array<string,string> $params
     * @return array<string,mixed>
     */
    public function retryFailed(array $params): array
    {
        $id = (int) $params['id'];
        $campaign = $this->campaigns->find($id);
        if (!in_array($campaign['send_state'], ['paused', 'done'], true)) {
            throw new HttpException(409, 'Pause the sending (or wait until it is done) before retrying failed recipients.');
        }
        $stmt = Database::connect()->prepare("UPDATE campaign_recipients SET status = 'pending', error = NULL, claimed_at = NULL WHERE campaign_id = ? AND status = 'failed'");
        $stmt->execute([$id]);
        $requeued = $stmt->rowCount();
        if ($requeued > 0 && $campaign['send_state'] === 'done') {
            Database::connect()->prepare("UPDATE campaigns SET send_state = 'paused', pause_reason = 'Failed recipients were queued again. Resume to send.' WHERE id = ?")->execute([$id]);
        }
        return ['data' => ['requeued' => $requeued] + $this->snapshot($id)];
    }

    private function transition(int $id, string $from, string $to, ?string $reason): array
    {
        $this->campaigns->find($id);
        $stmt = Database::connect()->prepare('UPDATE campaigns SET send_state = ?, pause_reason = ? WHERE id = ? AND send_state = ?');
        $stmt->execute([$to, $reason, $id, $from]);
        if ($stmt->rowCount() === 0) {
            throw new HttpException(409, "The campaign is not {$from}.");
        }
        return ['data' => $this->snapshot($id)];
    }

    /** @return array<string,mixed> */
    private function snapshot(int $id): array
    {
        $c = $this->campaigns->find($id);
        $stmt = Database::connect()->prepare("SELECT email, status, error, sent_at FROM campaign_recipients WHERE campaign_id = ? AND status IN ('failed','skipped','unknown') ORDER BY id LIMIT 200");
        $stmt->execute([$id]);
        return [
            'state' => $c['send_state'],
            'pause_reason' => $c['pause_reason'],
            'approved_by' => $c['approved_by'],
            'approved_at' => $c['approved_at'],
            'test_sent_at' => $c['test_sent_at'],
            'counts' => $this->counts($id),
            'rate_per_minute' => Queue::rate(),
            'dry_run' => Dispatch::dryRun(),
            'problems' => $stmt->fetchAll(),
        ];
    }

    /** @return array<string,int> */
    private function counts(int $id): array
    {
        $stmt = Database::connect()->prepare('SELECT status, COUNT(*) FROM campaign_recipients WHERE campaign_id = ? GROUP BY status');
        $stmt->execute([$id]);
        $counts = ['pending' => 0, 'sending' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0, 'unknown' => 0];
        foreach ($stmt->fetchAll(\PDO::FETCH_KEY_PAIR) as $status => $n) {
            $counts[$status] = (int) $n;
        }
        return $counts;
    }
}
