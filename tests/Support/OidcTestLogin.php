<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\Role;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Logs a test client in as an employee, without the login flow of the
 * provider. The tests of EmployeeAuthenticator test that flow.
 *
 * Example:
 *
 *     Database::reset($entityManager);
 *     OidcTestLogin::asEmployee($client);
 */
final class OidcTestLogin
{
    /**
     * The user has the role of an employee, but no time of a login, because
     * the user did not log in at the provider. The user is stored, because the
     * session loads it from the database on each request. Call it after
     * Database::reset().
     */
    public static function asEmployee(KernelBrowser $client): User
    {
        $user = new User('https://idp.example.com', 'employee');
        $user->update('Medarbejder', 'employee@example.com', [Role::EMPLOYEE]);
        $client->getContainer()->get(UserRepository::class)->save($user);
        $client->loginUser($user, 'main');

        return $user;
    }
}
