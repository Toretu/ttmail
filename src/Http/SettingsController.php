<?php
declare(strict_types=1);

namespace Mailings\Http;

use Mailings\Database;
use Mailings\Graph\GraphClient;
use Mailings\Graph\GraphException;

final class SettingsController
{
    /** @return array<string,mixed> */
    public function graph(): array
    {
        return ['data' => GraphClient::status()];
    }

    /**
     * Checks the app-only token, the granted permissions, and read access to each active mailbox.
     * Sends nothing and never returns the client secret.
     *
     * @return array<string,mixed>
     */
    public function testGraph(): array
    {
        $status = GraphClient::status();
        if (!$status['ready']) {
            throw new HttpException(422, 'Microsoft 365 is not configured', ['missing' => implode(', ', $status['missing'])]);
        }

        $client = new GraphClient();
        $result = ['token_ok' => false, 'error' => null, 'roles' => [], 'can_send' => false, 'can_read' => false, 'mailboxes' => []];
        try {
            $auth = $client->acquireToken();
        } catch (GraphException $e) {
            $result['error'] = $e->getMessage();
            return ['data' => $result];
        }

        $roles = $auth['roles'];
        $result['token_ok'] = true;
        $result['roles'] = $roles;
        $result['can_send'] = in_array('Mail.Send', $roles, true);
        $result['can_read'] = in_array('Mail.ReadWrite', $roles, true) || in_array('Mail.Read', $roles, true);

        $mailboxes = Database::connect()->query('SELECT id, display_name, email FROM mailboxes WHERE is_active = 1 ORDER BY id')->fetchAll();
        foreach ($mailboxes as $mailbox) {
            $entry = ['id' => (int) $mailbox['id'], 'email' => $mailbox['email'], 'ok' => false, 'inbox_items' => null, 'error' => null];
            try {
                $inbox = $client->get($auth['token'], '/users/' . rawurlencode($mailbox['email']) . '/mailFolders/inbox?$select=totalItemCount');
                $entry['ok'] = true;
                $entry['inbox_items'] = (int) ($inbox['totalItemCount'] ?? 0);
            } catch (GraphException $e) {
                $entry['error'] = $e->getMessage();
            }
            $result['mailboxes'][] = $entry;
        }
        return ['data' => $result];
    }
}
