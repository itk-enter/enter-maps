<?php

declare(strict_types=1);

namespace App\Renderer;

/**
 * The base maps that a configuration can use, by name.
 *
 * A configuration holds the name of a base map, not a URL. Thus a
 * configuration cannot load tiles from any server, and the list of tile hosts
 * is known for the Content-Security-Policy.
 */
final readonly class BaseMaps
{
    public const string LEAFLET_DEFAULT = 'osm';

    /**
     * The JSON Schema of the configuration has the same names.
     *
     * @return array<string, BaseMap>
     */
    public function leaflet(): array
    {
        return [
            'osm' => new BaseMap(
                url: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                maxZoom: 19,
            ),
        ];
    }
}
