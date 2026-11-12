<?php

declare(strict_types=1);

namespace App\MapConfig;

/**
 * An open filter: a control in the sidebar.
 *
 * A filter changes only the features of the layers in $appliesTo. The
 * features of other layers stay on the map. Each layer can keep the value in
 * a different attribute and with different codes.
 *
 * Example: "Kun gratis" applies to a layer of toilets, where
 * isAccessibleForFree is true, and to a layer of parking spots, where fee is
 * "no":
 *
 *     new Filter('toggle', 'Kun gratis', ['free' => 'Kun gratis'], [
 *         new FilterTarget('toilets', 'isAccessibleForFree', ['free' => true]),
 *         new FilterTarget('parking', 'fee', ['free' => 'no']),
 *     ]);
 */
final readonly class Filter
{
    /**
     * @param 'toggle'|'select'|'multiselect' $type
     * @param array<string, string>           $values    the codes of the filter, with the text that the control shows for each
     * @param list<FilterTarget>              $appliesTo
     */
    public function __construct(
        public string $type,
        public string $label,
        public array $values,
        public array $appliesTo,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['type'],
            $data['label'],
            $data['values'],
            array_map(FilterTarget::fromArray(...), $data['appliesTo']),
        );
    }
}
