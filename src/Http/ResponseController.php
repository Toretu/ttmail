<?php
declare(strict_types=1);

namespace Mailings\Http;

use Mailings\Database;
use Mailings\Schema;
use Mailings\Validator;

/** Editor review of answers from the public details page. */
final class ResponseController
{
    /** @param array<string,string> $params @return array<string,mixed> */
    public function list(array $params): array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare(
            'SELECT r.id, r.contact_id, r.changes, r.product_ids, r.comment, r.status, r.updated_at,
                    c.first_name, c.last_name, c.email, c.company_id, co.name AS company
             FROM campaign_responses r JOIN contacts c ON c.id = r.contact_id JOIN companies co ON co.id = c.company_id
             WHERE r.campaign_id = ? ORDER BY (r.status = \'new\') DESC, r.updated_at DESC LIMIT 500'
        );
        $stmt->execute([(int) $params['id']]);
        $rows = $stmt->fetchAll();

        $products = $pdo->prepare('SELECT id, name FROM campaign_products WHERE campaign_id = ?');
        $products->execute([(int) $params['id']]);
        $names = array_column($products->fetchAll(), 'name', 'id');

        $out = [];
        foreach ($rows as $row) {
            $changes = json_decode((string) $row['changes'], true) ?: [];
            $current = [
                'company' => $this->current('companies', (int) $row['company_id']),
                'contact' => $this->current('contacts', (int) $row['contact_id']),
            ];
            $diff = [];
            foreach ($changes as $group => $fields) {
                foreach ($fields as $field => $value) {
                    $diff[] = ['group' => $group, 'field' => $field, 'old' => $current[$group][$field] ?? null, 'new' => $value];
                }
            }
            $out[] = [
                'id' => (int) $row['id'], 'status' => $row['status'], 'updated_at' => $row['updated_at'],
                'contact' => trim($row['first_name'] . ' ' . $row['last_name']), 'email' => $row['email'], 'company' => $row['company'],
                'changes' => $diff,
                'products' => array_values(array_filter(array_map(static fn ($id) => $names[$id] ?? null, json_decode((string) $row['product_ids'], true) ?: []))),
                'comment' => $row['comment'],
            ];
        }
        return ['data' => $out];
    }

    /** @param array<string,string> $params @return array<string,mixed> */
    public function apply(array $params): array
    {
        $pdo = Database::connect();
        $response = $this->find($params);
        $changes = json_decode((string) $response['changes'], true) ?: [];
        $contact = $pdo->prepare('SELECT company_id FROM contacts WHERE id = ?');
        $contact->execute([$response['contact_id']]);
        $companyId = (int) $contact->fetchColumn();

        $pdo->beginTransaction();
        try {
            foreach (['company' => ['companies', ReplyController::COMPANY_FIELDS, Schema::companyRules(), $companyId], 'contact' => ['contacts', ReplyController::CONTACT_FIELDS, Schema::contactRules(), (int) $response['contact_id']]] as $group => [$table, $fields, $rules, $id]) {
                if (empty($changes[$group])) {
                    continue;
                }
                $values = Validator::clean($changes[$group] ?? [], array_intersect_key($rules, $fields), true);
                if ($values === []) {
                    continue;
                }
                $set = implode(', ', array_map(static fn (string $f): string => "{$f} = ?", array_keys($values)));
                $pdo->prepare("UPDATE {$table} SET {$set} WHERE id = ?")->execute([...array_values($values), $id]);
            }
            $pdo->prepare("UPDATE campaign_responses SET status = 'applied' WHERE id = ?")->execute([$response['id']]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return ['data' => ['status' => 'applied']];
    }

    /** @param array<string,string> $params @return array<string,mixed> */
    public function dismiss(array $params): array
    {
        $response = $this->find($params);
        Database::connect()->prepare("UPDATE campaign_responses SET status = 'dismissed' WHERE id = ?")->execute([$response['id']]);
        return ['data' => ['status' => 'dismissed']];
    }

    /** @param array<string,string> $params @return array<string,mixed> */
    private function find(array $params): array
    {
        $stmt = Database::connect()->prepare('SELECT * FROM campaign_responses WHERE id = ? AND campaign_id = ?');
        $stmt->execute([(int) $params['response_id'], (int) $params['id']]);
        $row = $stmt->fetch();
        if ($row === false) {
            throw new HttpException(404, 'Response not found');
        }
        return $row;
    }

    /** @return array<string,mixed> */
    private function current(string $table, int $id): array
    {
        $stmt = Database::connect()->prepare("SELECT * FROM {$table} WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: [];
    }
}
