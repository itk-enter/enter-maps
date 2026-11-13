<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Entity\MapConfiguration;
use App\Renderer\Renderer;
use App\Tests\Support\Database;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ListMapConfigCommandTest extends KernelTestCase
{
    private CommandTester $tester;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        Database::reset($this->entityManager);
        $this->tester = new CommandTester(new Application($kernel)->find('app:map-config:list'));
    }

    public function testListsTheConfigurationsWithTheirUrls(): void
    {
        $toilets = $this->store((object) ['version' => 1, 'title' => 'Toiletter']);
        $untitled = $this->store((object) ['version' => 1]);
        $deleted = $this->store((object) ['version' => 1, 'title' => 'Slettet']);
        $this->entityManager->remove($deleted);
        $this->entityManager->flush();

        $this->tester->execute([]);

        $this->assertSame(Command::SUCCESS, $this->tester->getStatusCode());
        $display = $this->tester->getDisplay(true);
        $this->assertStringContainsString('Toiletter', $display);
        $this->assertStringContainsString('/embed/leaflet/'.$toilets->getId(), $display);
        $this->assertStringContainsString('/embed/leaflet/'.$untitled->getId(), $display);
        $this->assertStringNotContainsString((string) $deleted->getId(), $display);
    }

    public function testTellsHowToImportIfThereAreNoConfigurations(): void
    {
        $this->tester->execute([]);

        $this->assertSame(Command::SUCCESS, $this->tester->getStatusCode());
        $this->assertStringContainsString('app:map-config:import', $this->tester->getDisplay(true));
    }

    private function store(\stdClass $config): MapConfiguration
    {
        $mapConfiguration = new MapConfiguration($config, [Renderer::Leaflet], []);
        $this->entityManager->persist($mapConfiguration);
        $this->entityManager->flush();

        return $mapConfiguration;
    }
}
