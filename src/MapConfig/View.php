<?php

declare(strict_types=1);

namespace App\MapConfig;

/**
 * The start view of a map.
 */
final readonly class View
{
    public function __construct(
        public float $longitude,
        public float $latitude,
        public float $zoom,
    ) {
    }

    /**
     * @param array{center: array{float|int, float|int}, zoom: float|int} $data
     */
    public static function fromArray(array $data): self
    {
        return new self((float) $data['center'][0], (float) $data['center'][1], (float) $data['zoom']);
    }
}
