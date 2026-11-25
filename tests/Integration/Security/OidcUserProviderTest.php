<?php

declare(strict_types=1);

namespace App\Tests\Integration\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\OidcUserProvider;
use App\Tests\Support\Database;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class OidcUserProviderTest extends KernelTestCase
{
    private const array EMPLOYEE = [
        'iss' => 'https://idp.example.com',
        'sub' => 'abc-123',
        'name' => 'Medarbejder',
        'email' => 'employee@example.com',
        'groups' => ['Other', 'Employee'],
    ];

    private OidcUserProvider $provider;

    private UserRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        Database::reset(self::getContainer()->get(EntityManagerInterface::class));
        $this->provider = self::getContainer()->get(OidcUserProvider::class);
        $this->repository = self::getContainer()->get(UserRepository::class);
    }

    public function testMakesAUserOnTheFirstLogin(): void
    {
        $user = $this->provider->loadUserByIdentifier('abc-123', self::EMPLOYEE);

        $this->assertCount(1, $this->repository->findAll());
        $this->assertSame('https://idp.example.com', $user->getIssuer());
        $this->assertSame('abc-123', $user->getSubject());
        $this->assertSame('Medarbejder', $user->getName());
        $this->assertSame('employee@example.com', $user->getEmail());
        $this->assertSame(['ROLE_EMPLOYEE', 'ROLE_USER'], $user->getRoles());
        $this->assertSame((string) $user->getId(), $user->getUserIdentifier());
    }

    public function testFindsTheSameUserOnALaterLoginAndUpdatesIt(): void
    {
        $first = $this->provider->loadUserByIdentifier('abc-123', self::EMPLOYEE);
        $firstLogin = $first->getLastLoginAt();
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        $later = $this->provider->loadUserByIdentifier('abc-123', ['name' => 'Ny Navn', 'groups' => []] + self::EMPLOYEE);

        $this->assertCount(1, $this->repository->findAll());
        $this->assertTrue($first->getId()->equals($later->getId()));
        $this->assertSame('Ny Navn', $later->getName());
        // The user left the group, so the user lost the role.
        $this->assertSame(['ROLE_USER'], $later->getRoles());
        $this->assertGreaterThanOrEqual($firstLogin, $later->getLastLoginAt());
    }

    public function testKeepsTheUsersOfTwoProvidersApart(): void
    {
        $this->provider->loadUserByIdentifier('abc-123', self::EMPLOYEE);
        $other = $this->provider->loadUserByIdentifier('abc-123', ['iss' => 'https://other.example.com'] + self::EMPLOYEE);

        $this->assertCount(2, $this->repository->findAll());
        $this->assertSame('https://other.example.com', $other->getIssuer());
    }

    public function testUsesTheIssuerOfTheConfigIfTheClaimsHaveNone(): void
    {
        $user = $this->provider->loadUserByIdentifier('abc-123', ['groups' => 'Employee']);

        $this->assertSame($_SERVER['OIDC_ISSUER'] ?? $_ENV['OIDC_ISSUER'], $user->getIssuer());
        $this->assertNull($user->getName());
        $this->assertNull($user->getEmail());
        // The groups must be a list.
        $this->assertSame(['ROLE_USER'], $user->getRoles());
    }

    public function testLoadsTheUserOfTheSessionFromTheDatabase(): void
    {
        $user = $this->provider->loadUserByIdentifier('abc-123', self::EMPLOYEE);
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        $fresh = $this->provider->refreshUser($user);

        $this->assertNotSame($user, $fresh);
        $this->assertTrue($user->getId()->equals($fresh->getId()));
        $this->assertTrue($this->provider->supportsClass(User::class));
        $this->assertFalse($this->provider->supportsClass(InMemoryUser::class));
    }

    public function testEndsTheSessionOfAUserWhoIsGone(): void
    {
        $user = new User('https://idp.example.com', 'gone');

        $this->expectException(UserNotFoundException::class);
        $this->provider->refreshUser($user);
    }

    public function testRejectsAnotherKindOfUser(): void
    {
        $this->expectException(UnsupportedUserException::class);
        $this->provider->refreshUser(new InMemoryUser('other', null));
    }
}
