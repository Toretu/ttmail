<?php
declare(strict_types=1);

namespace Mailings\Http;

use Mailings\Database;

final class ContactActions
{
    public function __construct(private readonly CrudController $contacts)
    {
    }

    /**
     * Marks the contact as unsubscribed so it is excluded from all future campaign sends.
     *
     * @param array<string,string> $params
     * @return array<string,mixed>
     */
    public function unsubscribe(array $params): array
    {
        $id = (int) $params['id'];
        $this->contacts->find($id);
        Database::connect()
            ->prepare("UPDATE contacts SET consent_status = 'withdrawn', unsubscribed_at = COALESCE(unsubscribed_at, CURRENT_TIMESTAMP) WHERE id = ?")
            ->execute([$id]);
        return ['data' => $this->contacts->find($id)];
    }
}
