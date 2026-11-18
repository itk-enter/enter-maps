<?php

declare(strict_types=1);

namespace App\Tests\Integration\Builder;

use App\Builder\MapConfigurationImporter;
use App\MapConfig\InvalidMapConfigException;
use App\Renderer\Renderer;
use App\Repository\MapConfigurationRepository;
use App\Tests\Support\Database;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class MapConfigurationImporterTest extends KernelTestCase
{
    private MapConfigurationImporter $importer;

    protected function setUp(): void
    {
        self::bootKernel();
        Database::reset(self::getContainer()->get(EntityManagerInterface::class));
        $this->importer = self::getContainer()->get(MapConfigurationImporter::class);
    }

    public function testStoresTheConfigurationAsANewMap(): void
    {
        $map = $this->importer->import((string) file_get_contents(\dirname(__DIR__, 2).'/Fixtures/map-config/toilets.json'));

        $stored = self::getContainer()->get(MapConfigurationRepository::class)->find((string) $map->getId());
        $this->assertNotNull($stored);
        $this->assertSame('Toiletter i Aarhus', $stored->getConfig()->title);
        $this->assertSame([Renderer::Leaflet], $stored->getRenderers());
        $this->assertNotNull($stored->getMapJson(Renderer::Leaflet));
    }

    public function testRejectsAConfigurationThatIsNotValid(): void
    {
        $this->expectException(InvalidMapConfigException::class);

        try {
            $this->importer->import('{"version": 1}');
        } finally {
            $this->assertSame([], self::getContainer()->get(MapConfigurationRepository::class)->findAll());
        }
    }
}
