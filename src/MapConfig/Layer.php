<?php

declare(strict_types=1);

namespace App\MapConfig;

/**
 * One data set from Enter on the map, with its own fields for the list row
 * and the detail view. The same data set can be on the map more than once,
 * for example with two different filters.
 */
final readonly class Layer
{
    /**
     * @param string                    $id     a short name, unique in the map; filters refer to it
     * @param array<string, mixed>|null $filter the closed filter, in CQL2-JSON; the data proxy makes the query for the broker from it
     * @param list<Field>               $detail the fields of the detail view
     */
    public function __construct(
        public string $id,
        public string $dataSet,
        public string $label,
        public string $colour,
        public ?string $icon,
        public ?array $filter,
        public Field $title,
        public ?Field $subtitle,
        public array $detail,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            dataSet: $data['dataSet'],
            label: $data['label'],
            colour: $data['colour'],
            icon: $data['icon'] ?? null,
            filter: $data['filter'] ?? null,
            title: Field::fromArray($data['row']['title']),
            subtitle: isset($data['row']['subtitle']) ? Field::fromArray($data['row']['subtitle']) : null,
            detail: array_map(Field::fromArray(...), $data['detail'] ?? []),
        );
    }
}
