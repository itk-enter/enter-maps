<?php

declare(strict_types=1);

namespace App\Tests\Integration\Security;

use App\Entity\Role;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\OidcUserFactory;
use App\Tests\Support\Database;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class OidcUserFactoryTest extends KernelTestCase
{
    private const array EMPLOYEE = [
        'iss' => 'https://idp.example.com',
        'sub' => 'abc-123',
        'name' => 'Medarbejder',
        'email' => 'employee@example.com',
        'groups' => ['Other', 'Employee'],
    ];

    private OidcUserFactory $factory;

    private UserRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        Database::reset(self::getContainer()->get(EntityManagerInterface::class));
        $this->factory = self::getContainer()->get(OidcUserFactory::class);
        $this->repository = self::getContainer()->get(UserRepository::class);
    }

    public function testMakesAUserOnTheFirstLogin(): void
    {
        $user = $this->factory->fromClaims(self::EMPLOYEE);

        $this->assertCount(1, $this->repository->findAll());
        $this->assertSame('https://idp.example.com', $user->getIssuer());
        $this->assertSame('abc-123', $user->getSubject());
        $this->assertSame('Medarbejder', $user->getName());
        $this->assertSame('employee@example.com', $user->getEmail());
        $this->assertSame(['ROLE_EMPLOYEE', 'ROLE_USER'], $user->getRoles());
        $this->assertSame((string) $user->getId(), $user->getUserIdentifier());
        $this->assertNotNull($user->getLastLoginAt());
    }

    public function testHasNoTimeOfALoginBeforeTheFirstLogin(): void
    {
        $user = new User('https://idp.example.com', 'abc-123');
        $user->update('Medarbejder', null, [Role::EMPLOYEE]);

        // A user can have data and roles without a login, for example a user that a test makes.
        $this->assertNull($user->getLastLoginAt());
        $this->assertSame(['ROLE_EMPLOYEE', 'ROLE_USER'], $user->getRoles());

        $at = new \DateTimeImmutable('2026-11-27 10:00');
        $user->logIn($at);
        $this->assertSame($at, $user->getLastLoginAt());
    }

    public function testFindsTheSameUserOnALaterLoginAndUpdatesIt(): void
    {
        $first = $this->factory->fromClaims(self::EMPLOYEE);
        $firstLogin = $first->getLastLoginAt();
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        $later = $this->factory->fromClaims(['name' => 'Nyt Navn', 'groups' => ['Other']] + self::EMPLOYEE);

        $this->assertCount(1, $this->repository->findAll());
        $this->assertTrue($first->getId()->equals($later->getId()));
        $this->assertSame('Nyt Navn', $later->getName());
        // The user left the group, so the user lost the role.
        $this->assertSame(['ROLE_USER'], $later->getRoles());
        $this->assertGreaterThanOrEqual($firstLogin, $later->getLastLoginAt());
    }

    public function testKeepsTheUsersOfTwoProvidersApart(): void
    {
        $this->factory->fromClaims(self::EMPLOYEE);
        $other = $this->factory->fromClaims(['iss' => 'https://other.example.com'] + self::EMPLOYEE);

        $this->assertCount(2, $this->repository->findAll());
        $this->assertSame('https://other.example.com', $other->getIssuer());
    }

    public function testReadsOnlyTheClaimsOfTheRightType(): void
    {
        $user = $this->factory->fromClaims(['iss' => 'https://idp.example.com', 'sub' => 'abc-123', 'name' => ['x'], 'groups' => 'Employee']);

        $this->assertNull($user->getName());
        $this->assertNull($user->getEmail());
        // The groups must be a list.
        $this->assertSame(['ROLE_USER'], $user->getRoles());
    }

    public function testNeedsTheIssuerAndTheSubject(): void
    {
        foreach ([['sub' => 'abc-123'], ['iss' => 'https://idp.example.com'], ['iss' => '', 'sub' => 'abc-123'], ['iss' => 'https://idp.example.com', 'sub' => 7]] as $claims) {
            try {
                $this->factory->fromClaims($claims);
                $this->fail('No exception for '.json_encode($claims, \JSON_THROW_ON_ERROR));
            } catch (\InvalidArgumentException $exception) {
                $this->assertSame('The ID token has no "iss" or no "sub" claim.', $exception->getMessage());
            }
        }
        $this->assertCount(0, $this->repository->findAll());
    }
}
