<?php
declare(strict_types=1);

namespace Mailings\Http;

use Mailings\Auth\Auth;
use Mailings\Database;
use Mailings\Mail\Replies;

/** Follow-up pane: replies from contacts, with a stage, a note and an optional follow-up date. */
final class ReplyInboxController
{
    public const STAGES = ['new', 'interested', 'needs_call', 'quote_sent', 'won', 'declined', 'done'];

    /** @return array<string,mixed> */
    public function list(): array
    {
        $where = [];
        $args = [];
        foreach (['stage', 'campaign_id'] as $f) {
            if (isset($_GET[$f]) && is_string($_GET[$f]) && $_GET[$f] !== '') {
                $where[] = "r.{$f} = ?";
                $args[] = $_GET[$f];
            }
        }
        if (!empty($_GET['due']) && $_GET['due'] === '1') {
            $where[] = "r.next_followup IS NOT NULL AND r.next_followup <= CURDATE() AND r.stage NOT IN ('won','declined','done')";
        }
        $pdo = Database::connect();
        $stmt = $pdo->prepare(
            'SELECT r.id, r.stage, r.subject, r.preview, r.received_at, r.note, r.next_followup, r.campaign_id, r.from_email,
                    c.first_name, c.last_name, co.name AS company, ca.name AS campaign
             FROM replies r JOIN contacts c ON c.id = r.contact_id JOIN companies co ON co.id = c.company_id
             LEFT JOIN campaigns ca ON ca.id = r.campaign_id'
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY r.received_at DESC LIMIT 300'
        );
        $stmt->execute($args);
        $counts = array_fill_keys(self::STAGES, 0);
        foreach ($pdo->query('SELECT stage, COUNT(*) n FROM replies GROUP BY stage')->fetchAll() as $row) {
            $counts[$row['stage']] = (int) $row['n'];
        }
        $sync = $pdo->query('SELECT m.email, s.last_run_at, s.last_error, s.last_seen, s.last_stored, s.last_unknown FROM mailboxes m LEFT JOIN mailbox_sync s ON s.mailbox_id = m.id WHERE m.is_active = 1')->fetchAll();
        return ['data' => $stmt->fetchAll(), 'meta' => ['stages' => $counts, 'sync' => $sync]];
    }

    /** @param array<string,string> $params @return array<string,mixed> */
    public function get(array $params): array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare(
            'SELECT r.*, c.first_name, c.last_name, c.company_id, co.name AS company, ca.name AS campaign
             FROM replies r JOIN contacts c ON c.id = r.contact_id JOIN companies co ON co.id = c.company_id
             LEFT JOIN campaigns ca ON ca.id = r.campaign_id WHERE r.id = ?'
        );
        $stmt->execute([(int) $params['id']]);
        $row = $stmt->fetch();
        if ($row === false) {
            throw new HttpException(404, 'Reply not found');
        }
        $history = $pdo->prepare('SELECT old_stage, new_stage, changed_by, changed_at FROM reply_stage_history WHERE reply_id = ? ORDER BY id DESC');
        $history->execute([$row['id']]);
        $row['history'] = $history->fetchAll();
        return ['data' => $row];
    }

    /** @param array<string,string> $params @return array<string,mixed> */
    public function update(array $params): array
    {
        $id = (int) $params['id'];
        $body = Json::body();
        $pdo = Database::connect();
        $stmt = $pdo->prepare('SELECT stage FROM replies WHERE id = ?');
        $stmt->execute([$id]);
        $old = $stmt->fetchColumn();
        if ($old === false) {
            throw new HttpException(404, 'Reply not found');
        }
        $set = [];
        $args = [];
        $errors = [];
        if (array_key_exists('stage', $body)) {
            if (!in_array($body['stage'], self::STAGES, true)) {
                $errors['stage'] = 'must be one of ' . implode(', ', self::STAGES);
            } else {
                $set[] = 'stage = ?';
                $args[] = $body['stage'];
            }
        }
        if (array_key_exists('note', $body)) {
            if ($body['note'] !== null && (!is_string($body['note']) || mb_strlen($body['note']) > 5000)) {
                $errors['note'] = 'must be text of at most 5000 characters';
            } else {
                $set[] = 'note = ?';
                $args[] = $body['note'] === null || trim($body['note']) === '' ? null : trim($body['note']);
            }
        }
        if (array_key_exists('next_followup', $body)) {
            $d = $body['next_followup'];
            if ($d !== null && $d !== '' && (!is_string($d) || \DateTime::createFromFormat('!Y-m-d', $d) === false || \DateTime::createFromFormat('!Y-m-d', $d)->format('Y-m-d') !== $d)) {
                $errors['next_followup'] = 'must be a date (YYYY-MM-DD)';
            } else {
                $set[] = 'next_followup = ?';
                $args[] = $d === '' ? null : $d;
            }
        }
        if ($errors !== []) {
            throw new HttpException(422, 'Validation failed', $errors);
        }
        if ($set === []) {
            throw new HttpException(422, 'No updatable fields supplied');
        }
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE replies SET ' . implode(', ', $set) . ' WHERE id = ?')->execute([...$args, $id]);
        if (isset($body['stage']) && $body['stage'] !== $old) {
            $pdo->prepare('INSERT INTO reply_stage_history (reply_id, old_stage, new_stage, changed_by) VALUES (?, ?, ?, ?)')
                ->execute([$id, $old, $body['stage'], (string) (Auth::user()['email'] ?? 'unknown')]);
        }
        $pdo->commit();
        return $this->get($params);
    }

    /** On-demand fetch from the Replies page. Bounded (2 pages per mailbox, longer time limit); cron does the full fetch. @return array<string,mixed> */
    public function sync(): array
    {
        set_time_limit(120);
        $r = (new Replies(2))->run();
        return ['data' => $r];
    }
}
