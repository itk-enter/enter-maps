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

    public function testImportsTheConfigurationsAndGivesTheirUrls(): void
    {
        $this->tester->execute(['files' => [self::fixture('toilets'), self::fixture('toilets-and-parking')]]);

        $this->assertSame(Command::SUCCESS, $this->tester->getStatusCode());
        $stored = self::getContainer()->get(MapConfigurationRepository::class)->findAll();
        $this->assertCount(2, $stored);
        $display = $this->tester->getDisplay(true);
        foreach ($stored as $mapConfiguration) {
            $this->assertStringContainsString('/embed/leaflet/'.$mapConfiguration->getId(), $display);
        }
    }

    public function testImportsTheValidFilesAndFailsForTheOthers(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'map-config');
        file_put_contents($file, '{"version": 1}');

        try {
            $this->tester->execute(['files' => [$file, '/does/not/exist.json', self::fixture('toilets')]]);
        } finally {
            unlink($file);
        }

        $this->assertSame(Command::FAILURE, $this->tester->getStatusCode());
        $display = $this->tester->getDisplay(true);
        $this->assertStringContainsString('is not a valid map configuration.', $display);
        $this->assertStringContainsString('Failed to read file', $display);
        $this->assertCount(1, self::getContainer()->get(MapConfigurationRepository::class)->findAll());
    }

    private static function fixture(string $name): string
    {
        return \dirname(__DIR__, 2).'/Fixtures/map-config/'.$name.'.json';
    }
}
