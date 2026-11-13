<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\MapConfiguration;
use App\Renderer\Renderer;
use App\Repository\MapConfigurationRepository;
use App\Tests\Support\Database;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class MapConfigurationRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private MapConfigurationRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->repository = self::getContainer()->get(MapConfigurationRepository::class);
        Database::reset($this->entityManager);
    }

    public function testStoresAndFindsAConfiguration(): void
    {
        $config = json_decode('{"version": 1, "title": "Toiletter", "empty": {}, "list": []}', false);
        $id = $this->store(new MapConfiguration($config, [Renderer::Leaflet], ['leaflet' => ['values' => new \stdClass(), 'list' => []]]));

        $found = $this->repository->find($id);

        $this->assertInstanceOf(MapConfiguration::class, $found);
        // An empty object stays an object, and an empty list stays a list.
        $this->assertSame('{"version":1,"title":"Toiletter","empty":{},"list":[]}', json_encode($found->getConfig()));
        $this->assertSame([Renderer::Leaflet], $found->getRenderers());
        $this->assertSame('{"values":{},"list":[]}', json_encode($found->getMapJson(Renderer::Leaflet)));
        $this->assertNotNull($found->getCreatedAt());
        $this->assertNotNull($found->getUpdatedAt());
    }

    public function testKeepsADeletedConfigurationButDoesNotFindIt(): void
    {
        $mapConfiguration = new MapConfiguration((object) ['version' => 1], [Renderer::Leaflet], []);
        $id = $this->store($mapConfiguration);

        $this->entityManager->remove($this->repository->find($id) ?? $mapConfiguration);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $this->assertNull($this->repository->find($id));

        $this->entityManager->getFilters()->disable('soft_delete');
        $deleted = $this->entityManager->find(MapConfiguration::class, $id);
        $this->assertInstanceOf(MapConfiguration::class, $deleted);
        $this->assertTrue($deleted->isDeleted());
    }

    private function store(MapConfiguration $mapConfiguration): string
    {
        $this->entityManager->persist($mapConfiguration);
        $this->entityManager->flush();
        $this->entityManager->clear();

        return (string) $mapConfiguration->getId();
    }
}
