<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\MapConfiguration;
use App\Renderer\Renderer;
use App\Repository\MapConfigurationRepository;
use App\Tests\Support\Database;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class MapConfigurationRepositoryTest extends KernelTestCase
{
    use ClockSensitiveTrait;

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

    public function testFindsTheActiveMapsWithTheLastChangedMapFirst(): void
    {
        $clock = self::mockTime('2026-11-16 10:00:00');
        $first = $this->map('First');
        $this->repository->save($first);
        $clock->sleep(60);
        $second = $this->map('Second');
        $this->repository->save($second);
        $clock->sleep(60);
        $deleted = $this->map('Deleted');
        $this->repository->save($deleted);
        $this->repository->delete($deleted);
        $this->entityManager->clear();

        $this->assertSame(['Second', 'First'], $this->titles($this->repository->findActive()));
    }

    public function testDeletesFindsAndRestoresADeletedMap(): void
    {
        $clock = self::mockTime('2026-11-16 10:00:00');
        $active = $this->map('Active');
        $first = $this->map('First deleted');
        $second = $this->map('Second deleted');
        foreach ([$active, $first, $second] as $map) {
            $this->repository->save($map);
        }
        $this->repository->delete($first);
        $clock->sleep(60);
        $this->repository->delete($second);
        $this->entityManager->clear();

        $this->assertSame(['Second deleted', 'First deleted'], $this->titles($this->repository->findDeleted()));
        $this->assertNull($this->repository->findDeletedById((string) $active->getId()), 'A map that is not deleted');
        $this->assertNull($this->repository->findDeletedById('01ARZ3NDEKTSV4RRFFQ69G5FAV'), 'A map that does not exist');
        // The filter is on again after the query. Doctrine keeps the loaded map,
        // so clear it first.
        $this->entityManager->clear();
        $this->assertNull($this->repository->find((string) $first->getId()));

        $deleted = $this->repository->findDeletedById((string) $first->getId());
        $this->assertInstanceOf(MapConfiguration::class, $deleted);
        $this->repository->restore($deleted);
        $this->entityManager->clear();

        $this->assertSame(['First deleted', 'Active'], $this->titles($this->repository->findActive()));
        $this->assertSame(['Second deleted'], $this->titles($this->repository->findDeleted()));
    }

    private function map(string $title): MapConfiguration
    {
        return new MapConfiguration((object) ['version' => 1, 'title' => $title], [Renderer::Leaflet], []);
    }

    /**
     * @param list<MapConfiguration> $maps
     *
     * @return list<mixed>
     */
    private function titles(array $maps): array
    {
        return array_map(static fn (MapConfiguration $map): mixed => $map->getConfig()->title ?? null, $maps);
    }

    private function store(MapConfiguration $mapConfiguration): string
    {
        $this->entityManager->persist($mapConfiguration);
        $this->entityManager->flush();
        $this->entityManager->clear();

        return (string) $mapConfiguration->getId();
    }
}
