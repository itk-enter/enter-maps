<?php

declare(strict_types=1);

namespace App\MapConfig;

/**
 * An open filter: a control in the sidebar.
 */
final readonly class Filter
{
    /**
     * @param 'toggle'|'select'|'multiselect' $type
     * @param array<string, string>           $values maps a code to a text; a Septima filter without values matches nothing
     */
    public function __construct(
        public string $path,
        public string $type,
        public string $label,
        public array $values,
    ) {
    }

    /**
     * @param array{path: string, type: 'toggle'|'select'|'multiselect', label: string, values: array<string, string>} $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['path'], $data['type'], $data['label'], $data['values']);
    }
}
