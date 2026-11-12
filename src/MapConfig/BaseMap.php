<?php

declare(strict_types=1);

namespace App\MapConfig;

/**
 * The name of the base map for each renderer. Null tells the converter to use
 * the default base map of the renderer.
 */
final readonly class BaseMap
{
    public function __construct(
        public ?string $leaflet,
        public ?string $septima,
    ) {
    }

    /**
     * @param array{leaflet?: string, septima?: string} $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['leaflet'] ?? null, $data['septima'] ?? null);
    }
}
