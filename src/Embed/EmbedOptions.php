<?php

declare(strict_types=1);

namespace App\Embed;

/**
 * The options of one embed of a map, after EmbedOptionsParser applied the
 * query string to the settings of the map.
 *
 * The values that the query string does not give come from the settings of
 * the map. $zoom, $center, $layers and $select are null when the map uses
 * its own view, all its layers and no selected place.
 */
final readonly class EmbedOptions
{
    /**
     * @param 'da'|'en'                $locale  the language of the texts on the page
     * @param array{float, float}|null $center  latitude and longitude
     * @param list<string>|null        $layers  the ids of the layers that are on at the start
     * @param 'left'|'right'|'off'     $sidebar the position of the sidebar, or no sidebar
     */
    public function __construct(
        public string $locale,
        public string $sidebar,
        public bool $list,
        public bool $report,
        public ?int $zoom = null,
        public ?array $center = null,
        public ?array $layers = null,
        public ?string $select = null,
    ) {
    }

    /**
     * The options that the map script uses.
     *
     * @return array{zoom: int|null, center: array{float, float}|null, layers: list<string>|null, select: string|null}
     */
    public function forScript(): array
    {
        return [
            'zoom' => $this->zoom,
            'center' => $this->center,
            'layers' => $this->layers,
            'select' => $this->select,
        ];
    }
}
