<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Security\OidcUserProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\OidcUser;

final class OidcUserProviderTest extends TestCase
{
    private OidcUserProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new OidcUserProvider('groups', 'Employee');
    }

    public function testGivesAnEmployeeTheRoleOfTheBuilder(): void
    {
        $user = $this->provider->loadUserByIdentifier('employee', ['groups' => ['Other', 'Employee'], 'name' => 'Medarbejder', 'email' => 'employee@example.com']);

        $this->assertSame('employee', $user->getUserIdentifier());
        $this->assertSame(['ROLE_USER', 'ROLE_EMPLOYEE'], $user->getRoles());
        $this->assertSame('Medarbejder', $user->getName());
        $this->assertSame('employee@example.com', $user->getEmail());
    }

    public function testGivesOtherUsersNoRoleOfTheBuilder(): void
    {
        foreach ([[], ['groups' => []], ['groups' => 'Employee'], ['groups' => ['employee']]] as $claims) {
            $user = $this->provider->loadUserByIdentifier('citizen', $claims);

            $this->assertSame(['ROLE_USER'], $user->getRoles(), json_encode($claims, \JSON_THROW_ON_ERROR));
            $this->assertNull($user->getName());
        }
    }

    public function testKeepsTheUserOfTheSession(): void
    {
        $user = new OidcUser(userIdentifier: 'employee', sub: 'employee');

        $this->assertSame($user, $this->provider->refreshUser($user));
        $this->assertTrue($this->provider->supportsClass(OidcUser::class));
        $this->assertFalse($this->provider->supportsClass(InMemoryUser::class));

        $this->expectException(UnsupportedUserException::class);
        $this->provider->refreshUser(new InMemoryUser('other', null));
    }
}
