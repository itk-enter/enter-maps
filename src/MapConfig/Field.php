<?php

declare(strict_types=1);

namespace App\MapConfig;

/**
 * A value from the properties of a feature, with the text to show for it.
 */
final readonly class Field
{
    /**
     * @param string                                         $path     a dot path into the GeoJSON properties
     * @param string|null                                    $fallback the text for a missing value; without it, the map leaves out the field
     * @param 'text'|'list'|'boolean'|'number'|'date'|'link' $format
     * @param array<string, string>                          $values   maps a code to a text
     */
    public function __construct(
        public string $path,
        public ?string $label,
        public ?string $fallback,
        public string $format,
        public array $values,
    ) {
    }

    /**
     * @param array{path: string, label?: string, fallback?: string, format?: 'text'|'list'|'boolean'|'number'|'date'|'link', values?: array<string, string>} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            path: $data['path'],
            label: $data['label'] ?? null,
            fallback: $data['fallback'] ?? null,
            format: $data['format'] ?? 'text',
            values: $data['values'] ?? [],
        );
    }
}
