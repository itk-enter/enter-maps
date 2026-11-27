<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Role;
use PHPUnit\Framework\TestCase;

final class RoleTest extends TestCase
{
    public function testGivesTheNameOfEachRoleWithTheValueAsKey(): void
    {
        $this->assertSame(['ROLE_USER' => 'USER', 'ROLE_EMPLOYEE' => 'EMPLOYEE'], Role::asArray());
        $this->assertSame(['ROLE_USER', 'ROLE_EMPLOYEE'], Role::values());
    }
}
