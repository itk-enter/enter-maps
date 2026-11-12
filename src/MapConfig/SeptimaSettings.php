<?php

declare(strict_types=1);

namespace App\MapConfig;

/**
 * The settings that only the Septima renderer uses.
 */
final readonly class SeptimaSettings
{
    /**
     * @param string|null $baseMap the name of the base map; null tells the converter to use its default
     */
    public function __construct(
        public ?string $baseMap,
    ) {
    }

    /**
     * @param array{baseMap?: string} $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['baseMap'] ?? null);
    }
}
