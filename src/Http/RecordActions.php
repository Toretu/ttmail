<?php
declare(strict_types=1);

namespace Mailings\Http;

use Mailings\Database;

/** Delete actions for companies and contacts. */
final class RecordActions
{
    public function __construct(
        private readonly CrudController $companies,
        private readonly CrudController $contacts,
    ) {
    }

    /**
     * Deletes a company and its contacts. Requires confirm_name equal to the company name.
     * Refused (409) when the company has registered sales; set its status to inactive instead.
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
        Database::connect()->prepare('DELETE FROM contacts WHERE id = ?')->execute([$id]);
        return ['data' => ['deleted' => $id]];
    }
}
