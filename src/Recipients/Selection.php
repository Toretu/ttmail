<?php
declare(strict_types=1);

namespace Mailings\Recipients;

use Mailings\Database;
use Mailings\Http\HttpException;

/**
 * Resolves "which contacts" from a request body: all contacts or members of groups,
 * optionally narrowed by company category and city.
 */
final class Selection
{
    /**
     * @param array<string,mixed> $body
     * @return list<array<string,mixed>> contacts with company status and the reason they are excluded (null when eligible)
     */
    public static function contacts(array $body): array
    {
        $all = ($body['all'] ?? false) === true;
        $groupIds = [];
        if (isset($body['group_ids'])) {
            if (!is_array($body['group_ids'])) {
                throw new HttpException(422, 'Validation failed', ['group_ids' => 'must be a list of group ids']);
            }
            $groupIds = array_values(array_unique(array_map('intval', $body['group_ids'])));
        }
        if (!$all && $groupIds === []) {
            throw new HttpException(422, 'Validation failed', ['selection' => 'choose all contacts or at least one group']);
        }

        $where = [];
        $params = [];
        $join = '';
        if (!$all) {
            $marks = implode(',', array_fill(0, count($groupIds), '?'));
            $join = "JOIN contact_group_members m ON m.contact_id = c.id AND m.group_id IN ({$marks})";
            $params = $groupIds;
        }
        foreach (['category', 'city'] as $field) {
            $value = $body[$field] ?? '';
            if (is_string($value) && trim($value) !== '') {
                $where[] = "co.{$field} = ?";
                $params[] = trim($value);
            }
        }
        $clause = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $stmt = Database::connect()->prepare(
            "SELECT DISTINCT c.id, c.first_name, c.last_name, c.email, c.consent_status, c.unsubscribed_at,
                    co.id AS company_id, co.name AS company, co.status AS company_status
             FROM contacts c JOIN companies co ON co.id = c.company_id {$join} {$clause} ORDER BY c.id"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['excluded'] = self::exclusion($row);
        }
        return $rows;
    }

    /** @param array<string,mixed> $row */
    public static function exclusion(array $row): ?string
    {
        if ($row['unsubscribed_at'] !== null || $row['consent_status'] === 'withdrawn') {
            return 'unsubscribed';
        }
        if ($row['company_status'] === 'do_not_contact') {
            return 'company_do_not_contact';
        }
        if ($row['company_status'] !== 'active') {
            return 'company_inactive';
        }
        if (filter_var($row['email'], FILTER_VALIDATE_EMAIL) === false) {
            return 'invalid_email';
        }
        return null;
    }
}
