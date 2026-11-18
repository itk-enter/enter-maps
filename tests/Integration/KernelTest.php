<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;
use Twig\Extension\CoreExtension;

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

    public function testShowsTheDatesOnThePagesInLocalTime(): void
    {
        self::bootKernel();
        $twig = self::getContainer()->get(Environment::class);

        $this->assertSame('Europe/Copenhagen', $twig->getExtension(CoreExtension::class)->getTimezone()->getName());
        // 10:02 in UTC is 11:02 in Copenhagen in the winter.
        $this->assertSame('11:02', $twig->createTemplate("{{ date|date('H:i') }}")->render(['date' => new \DateTimeImmutable('2026-11-18 10:02', new \DateTimeZone('UTC'))]));
    }
}
