<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Makes sure that the kernel boots in the test environment.
 */
final class KernelTest extends KernelTestCase
{
    public function testKernelBootsInTestEnvironment(): void
    {
        $kernel = self::bootKernel();

        $this->assertSame('test', $kernel->getEnvironment());
    }

    public function testUsesUtcAsTheDefaultTimeZone(): void
    {
        date_default_timezone_set('Europe/Copenhagen');

        self::bootKernel();

        $this->assertSame('UTC', date_default_timezone_get());
    }
}
