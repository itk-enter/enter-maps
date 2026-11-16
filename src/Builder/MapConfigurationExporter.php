<?php

declare(strict_types=1);

namespace App\Builder;

use App\Entity\MapConfiguration;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Gives the config of a map as a JSON file. The builder and
 * app:map-config:import can import the file again, for example on another
 * server.
 *
 * Example: the map "Toiletter i Aarhus" gives the file
 * "toiletter-i-aarhus-01M7ACK5C4TC8TM4A5Z88ZG2YM.json".
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

    public function filename(MapConfiguration $map): string
    {
        $title = $map->getConfig()->title ?? null;
        $slug = \is_string($title) ? $this->slugger->slug($title)->lower()->toString() : '';

        return ('' === $slug ? '' : $slug.'-').$map->getId().'.json';
    }
}
