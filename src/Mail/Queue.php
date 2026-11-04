<?php
declare(strict_types=1);

namespace Mailings\Mail;

use Mailings\Config;
use Mailings\Database;
use Mailings\Graph\GraphException;
use Mailings\Recipients\Selection;
use PDO;

/**
 * Sends pending recipients of approved campaigns, a few per run (meant to run every minute from cron).
 * Safety rules: a recipient is claimed before sending and never re-sent automatically; unsubscribed contacts
 * are re-checked right before sending; Microsoft limits, blocks and repeated failures pause the campaign.
 */
final class Queue
{
    private const MAX_CONSECUTIVE_FAILURES = 3;
    /** Statuses meaning "stop, the problem is not this recipient": auth, permission, throttling, service trouble, unreachable. */
    private const PAUSING_STATUSES = [0, 401, 403, 429, 500, 502, 503, 504, 509];

    public static function rate(): int
    {
        return min(30, max(1, (int) Config::get('MAIL_RATE_PER_MINUTE', '10')));
    }

    /** @return array{sent:int,failed:int,skipped:int,paused:int} */
    public function run(): array
    {
        $pdo = Database::connect();
        $lock = $pdo->query("SELECT GET_LOCK('mailings_send_queue', 0)")->fetchColumn();
        if ((int) $lock !== 1) {
            return ['sent' => 0, 'failed' => 0, 'skipped' => 0, 'paused' => 0];
        }
        try {
            return $this->process($pdo);
        } finally {
            $pdo->query("SELECT RELEASE_LOCK('mailings_send_queue')");
        }
    }

    /** @return array{sent:int,failed:int,skipped:int,paused:int} */
    private function process(PDO $pdo): array
    {
        $result = ['sent' => 0, 'failed' => 0, 'skipped' => 0, 'paused' => 0];

        // A recipient left in 'sending' means the worker died mid-send; the outcome is unknown, so never retry silently.
        $pdo->exec("UPDATE campaign_recipients SET status = 'failed', error = 'Outcome unknown (sending was interrupted). Check the mailbox Sent Items before retrying.'
                    WHERE status = 'sending' AND claimed_at < (NOW() - INTERVAL 10 MINUTE)");

        $budget = self::rate();
        $delay = self::dryRunDelay();
        $campaigns = $pdo->query("SELECT * FROM campaigns WHERE send_state = 'sending' ORDER BY approved_at, id")->fetchAll();

        foreach ($campaigns as $campaign) {
            $id = (int) $campaign['id'];
            if ($campaign['status'] === 'closed') {
                $this->pause($id, 'The campaign was closed.');
                $result['paused']++;
                continue;
            }
            $mailbox = $pdo->prepare('SELECT * FROM mailboxes WHERE id = ? AND is_active = 1');
            $mailbox->execute([$campaign['mailbox_id']]);
            $mailbox = $mailbox->fetch();
            if ($mailbox === false) {
                $this->pause($id, 'The sender mailbox is missing or inactive.');
                $result['paused']++;
                continue;
            }

            $consecutive = 0;
            while ($budget > 0) {
                $recipient = $this->claim($pdo, $id);
                if ($recipient === null) {
                    $left = $pdo->prepare("SELECT COUNT(*) FROM campaign_recipients WHERE campaign_id = ? AND status IN ('pending','sending')");
                    $left->execute([$id]);
                    if ((int) $left->fetchColumn() === 0) {
                        $pdo->prepare("UPDATE campaigns SET send_state = 'done', pause_reason = NULL WHERE id = ? AND send_state = 'sending'")->execute([$id]);
                    }
                    break;
                }

                $skip = Selection::exclusion($recipient);
                if ($skip !== null) {
                    $this->finish($pdo, (int) $recipient['rid'], 'skipped', $skip);
                    $result['skipped']++;
                    continue;
                }

                $budget--;
                try {
                    [$subject, $html] = Composer::build($campaign, $mailbox, (int) $recipient['contact_id']);
                    Dispatch::send($mailbox['email'], $recipient['email'], $subject, $html);
                    $this->finish($pdo, (int) $recipient['rid'], 'sent', null);
                    $result['sent']++;
                    $consecutive = 0;
                } catch (GraphException $e) {
                    if (in_array($e->httpStatus, self::PAUSING_STATUSES, true)) {
                        $pdo->prepare("UPDATE campaign_recipients SET status = 'pending', claimed_at = NULL WHERE id = ?")->execute([$recipient['rid']]);
                        $this->pause($id, 'Microsoft stopped the sending (HTTP ' . $e->httpStatus . '): ' . $e->getMessage());
                        $result['paused']++;
                        break;
                    }
                    $this->finish($pdo, (int) $recipient['rid'], 'failed', $e->getMessage());
                    $result['failed']++;
                    if (++$consecutive >= self::MAX_CONSECUTIVE_FAILURES) {
                        $this->pause($id, self::MAX_CONSECUTIVE_FAILURES . ' messages in a row failed. Last error: ' . $e->getMessage());
                        $result['paused']++;
                        break;
                    }
                } catch (\Throwable $e) {
                    error_log((string) $e);
                    $pdo->prepare("UPDATE campaign_recipients SET status = 'pending', claimed_at = NULL WHERE id = ?")->execute([$recipient['rid']]);
                    $this->pause($id, 'Unexpected error: ' . $e->getMessage());
                    $result['paused']++;
                    break;
                }
                if ($budget > 0 && $delay > 0) {
                    sleep($delay);
                }
            }
            if ($budget <= 0) {
                break;
            }
        }
        return $result;
    }

    /** Seconds between messages so one run of $rate messages takes just under a minute. */
    private static function dryRunDelay(): int
    {
        return Dispatch::dryRun() ? 0 : (int) floor(55 / self::rate());
    }

    /** @return array<string,mixed>|null */
    private function claim(PDO $pdo, int $campaignId): ?array
    {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            "SELECT r.id AS rid, r.contact_id, r.email, c.consent_status, c.unsubscribed_at, co.status AS company_status
             FROM campaign_recipients r JOIN contacts c ON c.id = r.contact_id JOIN companies co ON co.id = c.company_id
             WHERE r.campaign_id = ? AND r.status = 'pending' ORDER BY r.id LIMIT 1 FOR UPDATE"
        );
        $stmt->execute([$campaignId]);
        $row = $stmt->fetch();
        if ($row === false) {
            $pdo->commit();
            return null;
        }
        $pdo->prepare("UPDATE campaign_recipients SET status = 'sending', claimed_at = NOW() WHERE id = ?")->execute([$row['rid']]);
        $pdo->commit();
        return $row;
    }

    private function finish(PDO $pdo, int $recipientId, string $status, ?string $note): void
    {
        $pdo->prepare('UPDATE campaign_recipients SET status = ?, error = ?, sent_at = IF(? = \'sent\', NOW(), NULL), claimed_at = NULL WHERE id = ?')
            ->execute([$status, $note === null ? null : mb_substr($note, 0, 500), $status, $recipientId]);
    }

    private function pause(int $campaignId, string $reason): void
    {
        Database::connect()->prepare("UPDATE campaigns SET send_state = 'paused', pause_reason = ? WHERE id = ?")
            ->execute([mb_substr($reason, 0, 500), $campaignId]);
        error_log("Campaign {$campaignId} paused: {$reason}");
    }
}
