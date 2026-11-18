<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MapConfiguration;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * The queries of the bundle exclude a deleted map, because the soft delete
 * filter is on. findDeleted() and findDeletedById() turn the filter off for
 * one query.
 *
 * @extends ServiceEntityRepository<MapConfiguration>
 */
class MapConfigurationRepository extends ServiceEntityRepository
{
    private const string SOFT_DELETE_FILTER = 'soft_delete';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MapConfiguration::class);
    }

    /**
     * @return list<MapConfiguration> the maps that are not deleted, the last changed map first
     */
    public function findActive(): array
    {
        return array_values($this->findBy([], ['updatedAt' => 'DESC']));
    }

    /**
     * @return list<MapConfiguration> the deleted maps, the last deleted map first
     */
    public function findDeleted(): array
    {
        return $this->withoutSoftDeleteFilter(function (): array {
            /** @var list<MapConfiguration> $maps */
            $maps = $this->createQueryBuilder('map')
                ->where('map.deletedAt IS NOT NULL')
                ->orderBy('map.deletedAt', 'DESC')
                ->getQuery()
                ->getResult();

            return $maps;
        });
    }

    public function findDeletedById(string $id): ?MapConfiguration
    {
        $map = $this->withoutSoftDeleteFilter(fn (): ?MapConfiguration => $this->find($id));

        return $map?->isDeleted() ? $map : null;
    }

    public function save(MapConfiguration $map): void
    {
        $this->getEntityManager()->persist($map);
        $this->getEntityManager()->flush();
    }

    /**
     * Marks the map as deleted. The map stays in the database, and restore()
     * brings it back.
     */
    public function delete(MapConfiguration $map): void
    {
        $this->getEntityManager()->remove($map);
        $this->getEntityManager()->flush();
    }

    public function restore(MapConfiguration $map): void
    {
        $map->restore();
        $this->getEntityManager()->flush();
    }

    /**
     * @template T
     *
     * @param callable(): T $query
     *
     * @return T
     */
    private function withoutSoftDeleteFilter(callable $query): mixed
    {
        $filters = $this->getEntityManager()->getFilters();
        $filters->disable(self::SOFT_DELETE_FILTER);

        try {
            return $query();
        } finally {
            $filters->enable(self::SOFT_DELETE_FILTER);
        }
    }
}
