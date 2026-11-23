<?php

declare(strict_types=1);

namespace App\MapConfig;

/**
 * A condition of a layer that the user can turn on and off. A condition that
 * is on shows only the features of the layer that match it. The map applies
 * it in the browser, so the data proxy does not get it.
 */
final readonly class OpenFilter
{
    /**
     * @param string               $label     the text of the checkbox
     * @param array<string, mixed> $condition one comparison in CQL2-JSON, for example {"op": ">=", "args": [{"property": "seats"}, 2]}
     * @param bool                 $checked   true if the condition is on at the start
     */
    public function __construct(
        public string $label,
        public array $condition,
        public bool $checked,
    ) {
    }

    /**
     * @param array{label: string, condition: array<string, mixed>, checked?: bool} $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['label'], $data['condition'], $data['checked'] ?? false);
    }
}
