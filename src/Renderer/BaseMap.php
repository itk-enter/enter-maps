<?php

declare(strict_types=1);

namespace App\Renderer;

/**
 * A tile service for the background of a map.
 */
final readonly class BaseMap
{
    /**
     * @param string $url         the URL of the tiles, with {z}, {x} and {y}
     * @param string $attribution HTML that tells the source of the tiles, as the tile service requires
     */
    public function __construct(
        public string $url,
        public string $attribution,
        public int $maxZoom,
    ) {
    }
}
