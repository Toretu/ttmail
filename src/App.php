<?php
declare(strict_types=1);

namespace Mailings;

use Mailings\Auth\Auth;
use Mailings\Http\AuthController;
use Mailings\Http\CampaignController;
use Mailings\Http\ContactActions;
use Mailings\Http\CrudController;
use Mailings\Http\HttpException;
use Mailings\Http\ImportController;
use Mailings\Http\Json;
use Mailings\Http\RecordActions;
use Mailings\Http\GroupController;
use Mailings\Http\RecipientController;
use Mailings\Http\SettingsController;
use Mailings\Http\UserController;
use Mailings\Http\Router;

final class App
{
    public static function run(): never
    {
        try {
            $path = rtrim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/') ?: '/';
            if ($path === '/' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
                header('Location: /campaigns.html', true, 302);
                exit;
            }
            Auth::enforce($_SERVER['REQUEST_METHOD'] ?? 'GET', $path);
            $result = self::router()->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $path);
            $status = 200;
            if (is_array($result) && isset($result['_status'])) {
                $status = $result['_status'];
                unset($result['_status']);
            }
            Json::send($status, $result);
        } catch (HttpException $e) {
            Json::send($e->status, ['error' => $e->getMessage(), 'details' => $e->errors]);
        } catch (\Throwable $e) {
            error_log((string) $e);
            Json::send(500, ['error' => 'Internal server error']);
        }
    }

    private static function router(): Router
    {
        $companies = new CrudController(
            'companies',
            Schema::companyRules(),
            ['name', 'city', 'org_number'],
            ['status', 'category', 'city'],
            'Company'
        );
        $contacts = new CrudController(
            'contacts',
            Schema::contactRules(),
            ['first_name', 'last_name', 'email'],
            ['company_id', 'consent_status'],
            'Contact'
        );

        $campaigns = new CrudController(
            'campaigns',
            Schema::campaignRules(),
            ['name', 'description'],
            ['status'],
            'Campaign'
        );
        $campaignActions = new CampaignController($campaigns);
        $groupsCrud = new CrudController('contact_groups', Schema::groupRules(), ['name'], ['is_active'], 'Group');
        $groups = new GroupController($groupsCrud);
        $recipients = new RecipientController($campaigns);

        $recordActions = new RecordActions($companies, $contacts);
        $mailboxes = new CrudController('mailboxes', Schema::mailboxRules(), ['display_name', 'email'], ['is_active'], 'Mailbox');
        $settings = new SettingsController();
        $auth = new AuthController();
        $users = new CrudController('users', Schema::userRules(), ['email', 'display_name'], ['role', 'is_active'], 'User');
        $userActions = new UserController($users);

        $router = new Router();
        $router->add('GET', '/api/health', static function (): array {
            Database::connect()->query('SELECT 1');
            return ['status' => 'ok', 'environment' => Config::get('APP_ENV', 'production')];
        });
        $router->add('GET', '/api/companies', [$companies, 'list']);
        $router->add('POST', '/api/companies', [$companies, 'create']);
        $router->add('POST', '/api/companies/import', [new ImportController(), 'companies']);
        $router->add('GET', '/api/companies/{id}', [$companies, 'get']);
        $router->add('PUT', '/api/companies/{id}', [$companies, 'update']);
        $router->add('DELETE', '/api/companies/{id}', [$recordActions, 'deleteCompany']);
        $router->add('GET', '/api/contacts', [$contacts, 'list']);
        $router->add('POST', '/api/contacts', [$contacts, 'create']);
        $router->add('POST', '/api/contacts/import', [new ImportController(), 'contacts']);
        $router->add('GET', '/api/contacts/{id}', [$contacts, 'get']);
        $router->add('PUT', '/api/contacts/{id}', [$contacts, 'update']);
        $router->add('DELETE', '/api/contacts/{id}', [$recordActions, 'deleteContact']);
        $router->add('POST', '/api/contacts/{id}/unsubscribe', [new ContactActions($contacts), 'unsubscribe']);
        $router->add('GET', '/api/auth/config', [$auth, 'config']);
        $router->add('GET', '/api/me', [$auth, 'me']);
        $router->add('GET', '/auth/login', [$auth, 'login']);
        $router->add('GET', '/auth/callback', [$auth, 'callback']);
        $router->add('POST', '/auth/dev-login', [$auth, 'devLogin']);
        $router->add('POST', '/auth/logout', [$auth, 'logout']);
        $router->add('GET', '/api/users', [$users, 'list']);
        $router->add('POST', '/api/users', [$users, 'create']);
        $router->add('PUT', '/api/users/{id}', [$userActions, 'update']);
        $router->add('GET', '/api/settings/graph', [$settings, 'graph']);
        $router->add('POST', '/api/settings/graph/test', [$settings, 'testGraph']);
        $router->add('GET', '/api/mailboxes', [$mailboxes, 'list']);
        $router->add('POST', '/api/mailboxes', [$mailboxes, 'create']);
        $router->add('PUT', '/api/mailboxes/{id}', [$mailboxes, 'update']);
        $router->add('GET', '/api/campaigns', [$campaigns, 'list']);
        $router->add('POST', '/api/campaigns', [$campaigns, 'create']);
        $router->add('GET', '/api/campaigns/{id}', [$campaigns, 'get']);
        $router->add('PUT', '/api/campaigns/{id}', [$campaigns, 'update']);
        $router->add('DELETE', '/api/campaigns/{id}', [$campaignActions, 'deleteCampaign']);
        $router->add('GET', '/api/campaigns/{id}/products', [$campaignActions, 'products']);
        $router->add('POST', '/api/campaigns/{id}/products', [$campaignActions, 'createProduct']);
        $router->add('PUT', '/api/products/{id}', [$campaignActions, 'updateProduct']);
        $router->add('GET', '/api/campaigns/{id}/sales', [$campaignActions, 'sales']);
        $router->add('POST', '/api/campaigns/{id}/sales', [$campaignActions, 'createSale']);
        $router->add('PUT', '/api/sales/{id}', [$campaignActions, 'updateSale']);
        $router->add('GET', '/api/groups', [$groups, 'list']);
        $router->add('POST', '/api/groups', [$groupsCrud, 'create']);
        $router->add('GET', '/api/groups/{id}', [$groupsCrud, 'get']);
        $router->add('PUT', '/api/groups/{id}', [$groupsCrud, 'update']);
        $router->add('DELETE', '/api/groups/{id}', [$groups, 'delete']);
        $router->add('GET', '/api/groups/{id}/members', [$groups, 'members']);
        $router->add('POST', '/api/groups/{id}/members', [$groups, 'addMembers']);
        $router->add('DELETE', '/api/groups/{id}/members/{contact_id}', [$groups, 'removeMember']);
        $router->add('GET', '/api/campaigns/{id}/recipients', [$recipients, 'list']);
        $router->add('POST', '/api/campaigns/{id}/recipients', [$recipients, 'add']);
        $router->add('DELETE', '/api/campaigns/{id}/recipients/{contact_id}', [$recipients, 'remove']);
        $router->add('GET', '/api/campaigns/{id}/summary', [$campaignActions, 'summary']);
        return $router;
    }
}
