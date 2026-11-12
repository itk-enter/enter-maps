<?php

declare(strict_types=1);

namespace App\MapConfig;

/**
 * Where a layer keeps the value of a filter.
 */
final readonly class FilterTarget
{
    /**
     * @param string                                   $layer the id of a layer
     * @param string                                   $path  a dot path into the GeoJSON properties of the layer
     * @param array<int|string, string|int|float|bool> $codes the value in the data for each code of the filter; for a code that is not here, the value in the data is the code, as a text
     */
    public function __construct(
        public string $layer,
        public string $path,
        public array $codes,
    ) {
    }

    /**
     * @param array{layer: string, path: string, codes?: array<int|string, string|int|float|bool>} $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['layer'], $data['path'], $data['codes'] ?? []);
    }

    /**
     * Gets the value in the data for a code of the filter.
     *
     * The codes are keys of a JSON object, so they are texts. PHP changes a
     * key such as "1" into the integer 1, so $code can be an integer. Without
     * a value in $codes, the method returns the code as a text. If the data
     * holds a number, give the number in $codes.
     */
    public function valueFor(int|string $code): string|int|float|bool
    {
        return \array_key_exists($code, $this->codes) ? $this->codes[$code] : (string) $code;
    }
}
