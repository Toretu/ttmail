<?php
declare(strict_types=1);

namespace Mailings\Http;

use Mailings\Database;
use Mailings\Recipients\Selection;

/** Contact groups and their members. */
final class GroupController
{
    public function __construct(private readonly CrudController $groups)
    {
    }

    /** @return array<string,mixed> */
    public function list(): array
    {
        $q = trim((string) ($_GET['q'] ?? ''));
        $sql = 'SELECT g.*, (SELECT COUNT(*) FROM contact_group_members m WHERE m.group_id = g.id) AS member_count FROM contact_groups g';
        $params = [];
        if ($q !== '') {
            $sql .= ' WHERE g.name LIKE ?';
            $params[] = '%' . addcslashes($q, '%_\\') . '%';
        }
        $stmt = Database::connect()->prepare($sql . ' ORDER BY g.name');
        $stmt->execute($params);
        return ['data' => $stmt->fetchAll()];
    }

    /** @param array<string,string> $params @return array<string,mixed> */
    public function delete(array $params): array
    {
        $id = (int) $params['id'];
        $group = $this->groups->find($id);
        $body = Json::body();
        if (!is_string($body['confirm_name'] ?? null) || trim($body['confirm_name']) !== $group['name']) {
            throw new HttpException(422, 'Validation failed', ['confirm_name' => 'must exactly match the group name']);
        }
        Database::connect()->prepare('DELETE FROM contact_groups WHERE id = ?')->execute([$id]);
        return ['data' => ['deleted' => $id]];
    }

    /** @param array<string,string> $params @return array<string,mixed> */
    public function members(array $params): array
    {
        $id = (int) $params['id'];
        $this->groups->find($id);
        $stmt = Database::connect()->prepare(
            'SELECT c.id, c.first_name, c.last_name, c.email, c.consent_status, c.unsubscribed_at,
                    co.id AS company_id, co.name AS company, co.city, co.category, m.source, m.added_at
             FROM contact_group_members m
             JOIN contacts c ON c.id = m.contact_id JOIN companies co ON co.id = c.company_id
             WHERE m.group_id = ? ORDER BY co.name, c.last_name, c.first_name'
        );
        $stmt->execute([$id]);
        return ['data' => $stmt->fetchAll()];
    }

    /**
     * Adds contacts by explicit ids (contact_ids) or by filter (all, category, city). ?mode=preview only counts.
     *
     * @param array<string,string> $params
     * @return array<string,mixed>
     */
    public function addMembers(array $params): array
    {
        $id = (int) $params['id'];
        $this->groups->find($id);
        $body = Json::body();
        $pdo = Database::connect();

        if (isset($body['contact_ids'])) {
            if (!is_array($body['contact_ids']) || $body['contact_ids'] === []) {
                throw new HttpException(422, 'Validation failed', ['contact_ids' => 'must be a non-empty list']);
            }
            $ids = array_values(array_unique(array_map('intval', $body['contact_ids'])));
            $source = 'manual';
        } else {
            $filter = $body;
            $filter['all'] = true;
            $ids = array_map(static fn (array $c): int => (int) $c['id'], Selection::contacts($filter));
            $source = 'filter';
        }

        $existing = $pdo->prepare('SELECT contact_id FROM contact_group_members WHERE group_id = ?');
        $existing->execute([$id]);
        $already = array_flip(array_map('intval', $existing->fetchAll(\PDO::FETCH_COLUMN)));
        $new = array_values(array_filter($ids, static fn (int $c): bool => !isset($already[$c])));

        $added = 0;
        if (($_GET['mode'] ?? 'commit') !== 'preview') {
            $insert = $pdo->prepare('INSERT IGNORE INTO contact_group_members (group_id, contact_id, source) SELECT ?, id, ? FROM contacts WHERE id = ?');
            $pdo->beginTransaction();
            foreach ($new as $contactId) {
                $insert->execute([$id, $source, $contactId]);
                $added += $insert->rowCount();
            }
            $pdo->commit();
        }
        return ['data' => ['matched' => count($ids), 'already_members' => count($ids) - count($new), 'to_add' => count($new), 'added' => $added]];
    }

    /** @param array<string,string> $params @return array<string,mixed> */
    public function removeMember(array $params): array
    {
        $id = (int) $params['id'];
        $this->groups->find($id);
        $stmt = Database::connect()->prepare('DELETE FROM contact_group_members WHERE group_id = ? AND contact_id = ?');
        $stmt->execute([$id, (int) $params['contact_id']]);
        if ($stmt->rowCount() === 0) {
            throw new HttpException(404, 'Member not found');
        }
        return ['data' => ['removed' => (int) $params['contact_id']]];
    }
}
