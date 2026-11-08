<?php
declare(strict_types=1);

namespace Mailings\Http;

use Mailings\Database;
use Mailings\Recipients\Selection;

/** Who a campaign will be sent to. Unsubscribed contacts and inactive or do-not-contact companies are never added. */
final class RecipientController
{
    public function __construct(private readonly CrudController $campaigns)
    {
    }

    /** @param array<string,string> $params @return array<string,mixed> */
    public function list(array $params): array
    {
        $id = (int) $params['id'];
        $this->campaigns->find($id);
        $pdo = Database::connect();
        $stmt = $pdo->prepare(
            'SELECT r.contact_id, r.email, r.status, r.added_at, r.sent_at, r.error, c.first_name, c.last_name, co.name AS company
             FROM campaign_recipients r JOIN contacts c ON c.id = r.contact_id JOIN companies co ON co.id = c.company_id
             WHERE r.campaign_id = ? ORDER BY co.name, c.last_name, c.first_name'
        );
        $stmt->execute([$id]);
        $counts = $pdo->prepare('SELECT status, COUNT(*) FROM campaign_recipients WHERE campaign_id = ? GROUP BY status');
        $counts->execute([$id]);
        $byStatus = ['pending' => 0, 'sending' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0, 'unknown' => 0];
        foreach ($counts->fetchAll(\PDO::FETCH_KEY_PAIR) as $status => $n) {
            $byStatus[$status] = (int) $n;
        }
        return ['data' => $stmt->fetchAll(), 'meta' => ['total' => array_sum($byStatus), 'by_status' => $byStatus]];
    }

    /**
     * Body: {"all":true} or {"group_ids":[1,2]}, optionally narrowed with "category" and "city".
     * ?mode=preview returns the counts without changing anything.
     *
     * @param array<string,string> $params
     * @return array<string,mixed>
     */
    public function add(array $params): array
    {
        $id = (int) $params['id'];
        $campaign = $this->campaigns->find($id);
        if ($campaign['status'] === 'closed') {
            throw new HttpException(409, 'This campaign is closed. Recipients cannot be added.');
        }
        if (in_array($campaign['send_state'], ['sending', 'paused'], true)) {
            throw new HttpException(409, 'Sending is in progress or paused. Recipients cannot be added until it is done, because only the approved recipients may be sent to.');
        }
        $contacts = Selection::contacts(Json::body());

        $pdo = Database::connect();
        $existing = $pdo->prepare('SELECT contact_id FROM campaign_recipients WHERE campaign_id = ?');
        $existing->execute([$id]);
        $already = array_flip(array_map('intval', $existing->fetchAll(\PDO::FETCH_COLUMN)));

        $excluded = [];
        $toAdd = [];
        $alreadyCount = 0;
        foreach ($contacts as $c) {
            if (isset($already[(int) $c['id']])) {
                $alreadyCount++;
            } elseif ($c['excluded'] !== null) {
                $excluded[$c['excluded']] = ($excluded[$c['excluded']] ?? 0) + 1;
            } else {
                $toAdd[] = $c;
            }
        }

        $added = 0;
        if (($_GET['mode'] ?? 'commit') !== 'preview') {
            $insert = $pdo->prepare('INSERT IGNORE INTO campaign_recipients (campaign_id, contact_id, email) VALUES (?, ?, ?)');
            $pdo->beginTransaction();
            foreach ($toAdd as $c) {
                $insert->execute([$id, $c['id'], $c['email']]);
                $added += $insert->rowCount();
            }
            if ($added > 0 && $campaign['send_state'] === 'done') {
                $pdo->prepare("UPDATE campaigns SET send_state = 'draft', approved_by = NULL, approved_at = NULL WHERE id = ?")->execute([$id]);
            }
            $pdo->commit();
        }
        return ['data' => [
            'matched' => count($contacts),
            'to_add' => count($toAdd),
            'added' => $added,
            'already_in_campaign' => $alreadyCount,
            'excluded' => $excluded,
            'sample' => array_map(
                static fn (array $c): array => ['name' => trim($c['first_name'] . ' ' . $c['last_name']), 'company' => $c['company'], 'email' => $c['email']],
                array_slice($toAdd, 0, 10)
            ),
        ]];
    }

    /** @param array<string,string> $params @return array<string,mixed> */
    public function remove(array $params): array
    {
        $id = (int) $params['id'];
        $this->campaigns->find($id);
        $stmt = Database::connect()->prepare("DELETE FROM campaign_recipients WHERE campaign_id = ? AND contact_id = ? AND status = 'pending'");
        $stmt->execute([$id, (int) $params['contact_id']]);
        if ($stmt->rowCount() === 0) {
            throw new HttpException(404, 'Pending recipient not found (already sent recipients cannot be removed)');
        }
        return ['data' => ['removed' => (int) $params['contact_id']]];
    }
}
