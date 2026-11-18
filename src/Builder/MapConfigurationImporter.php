<?php

declare(strict_types=1);

namespace App\Builder;

use App\Entity\MapConfiguration;
use App\MapConfig\InvalidMapConfigException;
use App\MapConfig\MapConfigurationFactory;
use App\Renderer\Renderer;
use App\Repository\MapConfigurationRepository;

/**
 * Stores a map configuration from JSON as a new map, for the Leaflet
 * renderer. The builder and app:map-config:import use it.
 */
final readonly class MapConfigurationImporter
{
    public function __construct(
        private MapConfigurationFactory $factory,
        private MapConfigurationRepository $repository,
    ) {
    }

    /**
     * @throws InvalidMapConfigException if the JSON is not a valid map configuration
     */
    public function import(string $json): MapConfiguration
    {
        $map = $this->factory->create($json, [Renderer::Leaflet]);
        $this->repository->save($map);

        return $map;
    }
}
