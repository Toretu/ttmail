<?php
declare(strict_types=1);

namespace Mailings\Http;

use Mailings\Auth\Auth;

/** Admin management of who may sign in. Admins cannot change their own role or active state. */
final class UserController
{
    public function __construct(private readonly CrudController $users)
    {
    }

    /** @param array<string,string> $params @return array<string,mixed> */
    public function update(array $params): array
    {
        $id = (int) $params['id'];
        $me = Auth::user();
        if ($me !== null && (int) $me['id'] === $id) {
            $input = Json::body();
            foreach (['role', 'is_active'] as $locked) {
                if (array_key_exists($locked, $input)) {
                    throw new HttpException(409, 'You cannot change your own role or active state');
                }
            }
        }
        return $this->users->update($params);
    }
}
