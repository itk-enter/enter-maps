<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Repository\MapConfigurationRepository;
use App\Tests\Support\Database;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ImportMapConfigCommandTest extends KernelTestCase
{
    private CommandTester $tester;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        Database::reset(self::getContainer()->get(EntityManagerInterface::class));
        $this->tester = new CommandTester(new Application($kernel)->find('app:map-config:import'));
    }

    public function testImportsAConfiguration(): void
    {
        $this->tester->execute(['file' => \dirname(__DIR__, 2).'/Fixtures/map-config/toilets.json']);

        $this->assertSame(Command::SUCCESS, $this->tester->getStatusCode());
        $stored = self::getContainer()->get(MapConfigurationRepository::class)->findAll();
        $this->assertCount(1, $stored);
        $this->assertStringContainsString((string) $stored[0]->getId(), $this->tester->getDisplay());
    }

    public function testRejectsAConfigurationThatIsNotValid(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'map-config');
        file_put_contents($file, '{"version": 1}');

        try {
            $this->tester->execute(['file' => $file]);
        } finally {
            unlink($file);
        }

        $this->assertSame(Command::FAILURE, $this->tester->getStatusCode());
        $this->assertStringContainsString('The map configuration is not valid.', $this->tester->getDisplay());
        $this->assertSame([], self::getContainer()->get(MapConfigurationRepository::class)->findAll());
    }

    public function testGivesAnErrorIfTheFileDoesNotExist(): void
    {
        $this->tester->execute(['file' => '/does/not/exist.json']);

        $this->assertSame(Command::FAILURE, $this->tester->getStatusCode());
        $this->assertStringContainsString('Failed to read file', $this->tester->getDisplay());
    }
}
