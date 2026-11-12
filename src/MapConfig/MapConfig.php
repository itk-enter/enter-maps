<?php

declare(strict_types=1);

namespace App\MapConfig;

/**
 * The configuration of one map, after validation against the JSON Schema.
 *
 * The converters make the settings of each renderer from this object. Make
 * it with MapConfigParser, which validates the JSON first.
 */
final readonly class MapConfig
{
    /**
     * @param 'da-DK'|'en-GB' $locale
     * @param float|null      $minZoom below this zoom, the map does not show the features
     * @param list<Layer>     $layers
     * @param list<Filter>    $filters the open filters in the sidebar
     */
    public function __construct(
        public int $version,
        public string $title,
        public string $locale,
        public ?View $view,
        public BaseMap $baseMap,
        public ?float $minZoom,
        public Sidebar $sidebar,
        public array $layers,
        public array $filters,
        public ?Report $report,
    ) {
    }

    /**
     * @param array<string, mixed> $data JSON that is valid against the schema
     */
    public static function fromArray(array $data): self
    {
        return new self(
            version: $data['version'],
            title: $data['title'],
            locale: $data['locale'] ?? 'da-DK',
            view: isset($data['view']) ? View::fromArray($data['view']) : null,
            baseMap: BaseMap::fromArray($data['baseMap'] ?? []),
            minZoom: isset($data['zoomGate']) ? (float) $data['zoomGate']['minZoom'] : null,
            sidebar: Sidebar::fromArray($data['sidebar'] ?? []),
            layers: array_map(Layer::fromArray(...), $data['layers']),
            filters: array_map(Filter::fromArray(...), $data['filters'] ?? []),
            report: isset($data['report']) ? Report::fromArray($data['report']) : null,
        );
    }
}
