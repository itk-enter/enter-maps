<?php

declare(strict_types=1);

namespace App\MapConfig;

/**
 * One data set from Enter on the map.
 */
final readonly class Layer
{
    /**
     * @param string|null $where  the closed filter: an NGSI-LD query that the entities must also match
     * @param list<Field> $detail the fields of the detail view
     */
    public function __construct(
        public string $dataSet,
        public string $label,
        public ?string $colour,
        public ?string $icon,
        public ?string $where,
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
            dataSet: $data['dataSet'],
            label: $data['label'],
            colour: $data['colour'] ?? null,
            icon: $data['icon'] ?? null,
            where: $data['where'] ?? null,
            title: Field::fromArray($data['row']['title']),
            subtitle: isset($data['row']['subtitle']) ? Field::fromArray($data['row']['subtitle']) : null,
            detail: array_map(Field::fromArray(...), $data['detail'] ?? []),
        );
    }
}
