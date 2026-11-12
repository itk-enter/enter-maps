<?php

declare(strict_types=1);

namespace App\MapConfig;

/**
 * Where a layer keeps the value of a filter.
 */
final readonly class FilterTarget
{
    /**
     * @param string                               $layer the id of a layer
     * @param string                               $path  a dot path into the GeoJSON properties of the layer
     * @param array<string, string|int|float|bool> $codes the value in the data for each code of the filter; for a code that is not here, the value in the data is the code
     */
    public function __construct(
        public string $layer,
        public string $path,
        public array $codes,
    ) {
    }

    /**
     * @param array{layer: string, path: string, codes?: array<string, string|int|float|bool>} $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['layer'], $data['path'], $data['codes'] ?? []);
    }

    /**
     * Gets the value in the data for a code of the filter.
     */
    public function valueFor(string $code): string|int|float|bool
    {
        return \array_key_exists($code, $this->codes) ? $this->codes[$code] : $code;
    }
}
