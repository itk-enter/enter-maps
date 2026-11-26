<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\OidcUserFactory;
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
     * The user is stored, because the session loads it from the database on
     * each request. Call it after Database::reset().
     */
    public static function asEmployee(KernelBrowser $client): User
    {
        $user = new User('https://idp.example.com', 'employee');
        $user->logIn('Medarbejder', 'employee@example.com', [OidcUserFactory::ROLE_EMPLOYEE], new \DateTimeImmutable());
        $client->getContainer()->get(UserRepository::class)->save($user);
        $client->loginUser($user, 'main');

        return $user;
    }
}
