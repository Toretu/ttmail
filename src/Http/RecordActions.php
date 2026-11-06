<?php
declare(strict_types=1);

namespace Mailings\Http;

use Mailings\Database;

/**
 * Delete actions for companies and contacts.
 * A contact that has unsubscribed, or that has been sent campaign mail, is never deleted: the unsubscribe
 * record and the send history must survive a re-import of the same address. Set the status instead.
 */
final class RecordActions
{
    public function __construct(
        private readonly CrudController $companies,
        private readonly CrudController $contacts,
    ) {
    }

    /**
     * Deletes a company and its contacts. Requires confirm_name equal to the company name.
     * Refused (409) when the company has registered sales or a contact that must be kept; set its status to inactive instead.
     *
     * @param array<string,string> $params
     * @return array<string,mixed>
     */
    public function deleteCompany(array $params): array
    {
        $id = (int) $params['id'];
        $company = $this->companies->find($id);
        $body = Json::body();
        if (!is_string($body['confirm_name'] ?? null) || trim($body['confirm_name']) !== $company['name']) {
            throw new HttpException(422, 'Validation failed', ['confirm_name' => 'must exactly match the company name']);
        }

        $pdo = Database::connect();
        $pdo->beginTransaction();
        try {
            $sales = $pdo->prepare('SELECT COUNT(*) FROM product_sales WHERE company_id = ? FOR UPDATE');
            $sales->execute([$id]);
            if ((int) $sales->fetchColumn() > 0) {
                throw new HttpException(409, 'This company has registered sales and cannot be deleted. Set its status to inactive instead.');
            }
            $kept = $pdo->prepare('SELECT COUNT(*) FROM contacts WHERE company_id = ? AND ' . self::mustKeepSql() . ' FOR UPDATE');
            $kept->execute([$id]);
            if ((int) $kept->fetchColumn() > 0) {
                throw new HttpException(409, 'This company has contacts that have unsubscribed or have been sent campaign mail, so it cannot be deleted. Set its status to inactive or do not contact instead.');
            }
            $contacts = $pdo->prepare('DELETE FROM contacts WHERE company_id = ?');
            $contacts->execute([$id]);
            $pdo->prepare('DELETE FROM companies WHERE id = ?')->execute([$id]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        return ['data' => ['deleted' => $id, 'contacts_deleted' => $contacts->rowCount()]];
    }

    /** @param array<string,string> $params @return array<string,mixed> */
    public function deleteContact(array $params): array
    {
        $id = (int) $params['id'];
        $this->contacts->find($id);
        $pdo = Database::connect();
        $kept = $pdo->prepare('SELECT COUNT(*) FROM contacts WHERE id = ? AND ' . self::mustKeepSql());
        $kept->execute([$id]);
        if ((int) $kept->fetchColumn() > 0) {
            throw new HttpException(409, 'This contact has unsubscribed or has been sent campaign mail, so it cannot be deleted. The record is needed so the address is never mailed again. Mark it withdrawn or set the company to do not contact instead.');
        }
        $pdo->prepare('DELETE FROM contacts WHERE id = ?')->execute([$id]);
        return ['data' => ['deleted' => $id]];
    }

    /** Condition on the contacts table (alias-free) for contacts that must be kept. */
    private static function mustKeepSql(): string
    {
        return "(unsubscribed_at IS NOT NULL OR consent_status = 'withdrawn'
            OR EXISTS (SELECT 1 FROM campaign_recipients r WHERE r.contact_id = contacts.id AND r.status IN ('sending','sent','unknown')))";
    }
}
