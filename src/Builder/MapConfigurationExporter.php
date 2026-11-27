<?php

declare(strict_types=1);

namespace App\Builder;

use App\Entity\MapConfiguration;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Writes the config of a map as a JSON file, which the import of the builder
 * and app:map-config:import accept. The file has only the config, not the
 * id of the map, so an import makes a new map with its own id.
 *
 * Example: the map "Toiletter i Aarhus" gives the file
 * "toiletter-i-aarhus.json".
 */
final readonly class MapConfigurationExporter
{
    public function __construct(
        private SluggerInterface $slugger,
    ) {
    }

    public function json(MapConfiguration $map): string
    {
        return json_encode($map->getConfig(), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR)."\n";
    }

    /**
     * The name of the file, from the title of the map. A map without a title gets its id.
     */
    public function fileName(MapConfiguration $map): string
    {
        $title = $map->getConfig()->title ?? null;
        $name = \is_string($title) ? $this->slugger->slug($title)->lower()->toString() : '';

        return ('' === $name ? (string) $map->getId() : $name).'.json';
    }
}
