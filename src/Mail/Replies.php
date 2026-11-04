<?php
declare(strict_types=1);

namespace Mailings\Mail;

use Mailings\Database;
use Mailings\Graph\GraphClient;
use Mailings\Graph\GraphException;

/** Fetches inbox mail for the approved mailboxes and stores replies from known contacts. */
final class Replies
{
    private const MAX_PAGES = 10;

    /** @return array{seen:int,stored:int,unknown:int,error:?string} totals over all active mailboxes */
    public function run(): array
    {
        $pdo = Database::connect();
        if ((int) $pdo->query("SELECT GET_LOCK('mailings_fetch_replies', 0)")->fetchColumn() !== 1) {
            return ['seen' => 0, 'stored' => 0, 'unknown' => 0, 'error' => 'Another fetch is already running'];
        }
        $total = ['seen' => 0, 'stored' => 0, 'unknown' => 0, 'error' => null];
        try {
            if (Dispatch::dryRun()) {
                return $total + ['error' => 'Dry run: mailboxes are not read'];
            }
            if (!GraphClient::status()['ready']) {
                return ['seen' => 0, 'stored' => 0, 'unknown' => 0, 'error' => 'The Microsoft 365 connection is not configured yet. See Settings.'];
            }
            $client = new GraphClient();
            $token = $client->acquireToken()['token'];
            foreach ($pdo->query('SELECT id, email FROM mailboxes WHERE is_active = 1')->fetchAll() as $mailbox) {
                $r = $this->mailbox($client, $token, (int) $mailbox['id'], (string) $mailbox['email']);
                foreach (['seen', 'stored', 'unknown'] as $k) {
                    $total[$k] += $r[$k];
                }
                $total['error'] ??= $r['error'];
            }
        } catch (GraphException $e) {
            $total['error'] = $e->getMessage();
        } finally {
            $pdo->query("SELECT RELEASE_LOCK('mailings_fetch_replies')");
        }
        return $total;
    }

    /** @return array{seen:int,stored:int,unknown:int,error:?string} */
    private function mailbox(GraphClient $client, string $token, int $mailboxId, string $email): array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare('SELECT last_received FROM mailbox_sync WHERE mailbox_id = ?');
        $stmt->execute([$mailboxId]);
        $last = $stmt->fetchColumn();
        $since = gmdate('Y-m-d\TH:i:s\Z', $last ? strtotime($last . ' UTC') : time() - 30 * 86400);

        $messages = [];
        $error = null;
        try {
            $url = '/users/' . rawurlencode($email) . '/mailFolders/inbox/messages?' . http_build_query([
                '$filter' => "receivedDateTime ge {$since}",
                '$orderby' => 'receivedDateTime asc',
                '$top' => 50,
                '$select' => 'id,subject,from,receivedDateTime,bodyPreview,body',
            ], '', '&', PHP_QUERY_RFC3986);
            for ($page = 0; $url !== null && $page < self::MAX_PAGES; $page++) {
                $res = $client->getPage($token, $url, ['Prefer: outlook.body-content-type="text"']);
                array_push($messages, ...($res['value'] ?? []));
                $url = is_string($res['@odata.nextLink'] ?? null) ? $res['@odata.nextLink'] : null;
            }
        } catch (GraphException $e) {
            $error = $e->getMessage();
        }

        $result = $this->store($mailboxId, $email, $messages);
        $newest = $result['newest'] ?? ($last ?: null);
        $pdo->prepare(
            'INSERT INTO mailbox_sync (mailbox_id, last_received, last_run_at, last_error, last_seen, last_stored, last_unknown)
             VALUES (?, ?, UTC_TIMESTAMP(), ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE last_received = VALUES(last_received), last_run_at = VALUES(last_run_at), last_error = VALUES(last_error),
               last_seen = VALUES(last_seen), last_stored = VALUES(last_stored), last_unknown = VALUES(last_unknown)'
        )->execute([$mailboxId, $newest, $error, $result['seen'], $result['stored'], $result['unknown']]);
        return ['seen' => $result['seen'], 'stored' => $result['stored'], 'unknown' => $result['unknown'], 'error' => $error];
    }

    /**
     * Stores messages (Graph message resources) from known contacts. Mail from other senders is counted but not kept.
     *
     * @param list<array<string,mixed>> $messages
     * @return array{seen:int,stored:int,unknown:int,newest:?string}
     */
    public function store(int $mailboxId, string $mailboxEmail, array $messages): array
    {
        $pdo = Database::connect();
        $contactStmt = $pdo->prepare('SELECT id FROM contacts WHERE email = ? LIMIT 1');
        $campaignStmt = $pdo->prepare("SELECT campaign_id FROM campaign_recipients WHERE contact_id = ? AND status = 'sent' AND sent_at <= ? ORDER BY sent_at DESC LIMIT 1");
        $insert = $pdo->prepare(
            'INSERT IGNORE INTO replies (mailbox_id, graph_id, contact_id, campaign_id, from_email, subject, preview, body_text, received_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $out = ['seen' => 0, 'stored' => 0, 'unknown' => 0, 'newest' => null];
        foreach ($messages as $m) {
            $out['seen']++;
            $from = strtolower(trim((string) ($m['from']['emailAddress']['address'] ?? '')));
            $received = gmdate('Y-m-d H:i:s', (int) strtotime((string) ($m['receivedDateTime'] ?? 'now')));
            if ($out['newest'] === null || $received > $out['newest']) {
                $out['newest'] = $received;
            }
            $contactStmt->execute([$from]);
            $contactId = $contactStmt->fetchColumn();
            if ($from === '' || $from === strtolower($mailboxEmail) || $contactId === false) {
                $out['unknown']++;
                continue;
            }
            $campaignStmt->execute([$contactId, $received]);
            $campaign = $campaignStmt->fetchColumn();
            $insert->execute([
                $mailboxId, (string) ($m['id'] ?? ''), $contactId, $campaign === false ? null : $campaign, $from,
                mb_substr((string) ($m['subject'] ?? ''), 0, 500), mb_substr((string) ($m['bodyPreview'] ?? ''), 0, 500),
                mb_substr((string) ($m['body']['content'] ?? ''), 0, 20000), $received,
            ]);
            $out['stored'] += $insert->rowCount();
        }
        return $out;
    }
}
