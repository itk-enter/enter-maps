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
        $this->tester->execute(['files' => [$this->fixture('toilets'), $this->fixture('toilets-and-parking')]]);

        $this->assertSame(Command::SUCCESS, $this->tester->getStatusCode());
        $stored = self::getContainer()->get(MapConfigurationRepository::class)->findAll();
        $this->assertCount(2, $stored);
        $display = $this->tester->getDisplay(true);
        foreach ($stored as $mapConfiguration) {
            $this->assertStringContainsString('/embed/leaflet/'.$mapConfiguration->getId(), $display);
            $this->assertStringContainsString(strtoupper(bin2hex($mapConfiguration->getId()->toBinary())), $display);
        }
    }

    public function testImportsTheValidFilesAndFailsForTheOthers(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'map-config');
        file_put_contents($file, '{"version": 1}');

        try {
            $this->tester->execute(['files' => [$file, '/does/not/exist.json', $this->fixture('toilets')]]);
        } finally {
            unlink($file);
        }

        $this->assertSame(Command::FAILURE, $this->tester->getStatusCode());
        $display = $this->tester->getDisplay(true);
        $this->assertSame(1, substr_count($display, 'This is fixture data'));
        $this->assertStringContainsString('is not a valid map configuration.', $display);
        $this->assertStringContainsString('Failed to read file', $display);
        $this->assertCount(1, self::getContainer()->get(MapConfigurationRepository::class)->findAll());
    }

    public function testGivesTheImportedMapsAsJson(): void
    {
        $this->tester->execute(['files' => [$this->fixture('toilets')], '--format' => 'json']);

        $this->assertSame(Command::SUCCESS, $this->tester->getStatusCode());
        $stored = self::getContainer()->get(MapConfigurationRepository::class)->findAll();
        /** @var list<array{file: string, id: string, url: string, fixture: bool}> $maps */
        $maps = json_decode($this->tester->getDisplay(), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertCount(1, $maps);
        $this->assertSame([$this->fixture('toilets'), (string) $stored[0]->getId(), true], [$maps[0]['file'], $maps[0]['id'], $maps[0]['fixture']]);
        $this->assertStringEndsWith('/embed/leaflet/'.$stored[0]->getId(), $maps[0]['url']);
    }

    public function testWritesTheErrorsToStderrWithJson(): void
    {
        $this->tester->execute(['files' => ['/does/not/exist.json', $this->fixture('toilets')], '--format' => 'json'], ['capture_stderr_separately' => true]);

        $this->assertSame(Command::FAILURE, $this->tester->getStatusCode());
        // Stdout has only the JSON, with the one map that the command imported.
        $this->assertCount(1, json_decode($this->tester->getDisplay(), true, 512, \JSON_THROW_ON_ERROR));
        $this->assertStringContainsString('Failed to read file', $this->tester->getErrorOutput());
    }

    public function testRejectsAnUnknownFormat(): void
    {
        $this->tester->execute(['files' => [$this->fixture('toilets')], '--format' => 'xml']);

        $this->assertSame(Command::INVALID, $this->tester->getStatusCode());
        $this->assertStringContainsString('The format "xml" is not known.', $this->tester->getDisplay(true));
        $this->assertSame([], self::getContainer()->get(MapConfigurationRepository::class)->findAll());
    }

    private function fixture(string $name): string
    {
        return \dirname(__DIR__, 2).'/Fixtures/map-config/'.$name.'.json';
    }
}
