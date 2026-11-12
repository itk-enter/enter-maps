<?php

declare(strict_types=1);

namespace App\MapConfig;

/**
 * The configuration of one map, after validation against the JSON Schema.
 *
 * The converters make the settings of each renderer from this object. Most
 * settings are the same for all renderers. $leaflet and $septima hold the
 * settings that only one renderer uses. Make it with MapConfigParser, which
 * validates the JSON first.
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
        public ?string $description,
        public string $locale,
        public ?View $view,
        public ?float $minZoom,
        public Sidebar $sidebar,
        public array $layers,
        public array $filters,
        public ?Report $report,
        public LeafletSettings $leaflet,
        public SeptimaSettings $septima,
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
            description: $data['description'] ?? null,
            locale: $data['locale'] ?? 'da-DK',
            view: isset($data['view']) ? View::fromArray($data['view']) : null,
            minZoom: isset($data['zoomGate']) ? (float) $data['zoomGate']['minZoom'] : null,
            sidebar: Sidebar::fromArray($data['sidebar'] ?? []),
            layers: array_map(Layer::fromArray(...), $data['layers']),
            filters: array_map(Filter::fromArray(...), $data['filters'] ?? []),
            report: isset($data['report']) ? Report::fromArray($data['report']) : null,
            leaflet: LeafletSettings::fromArray($data['leaflet'] ?? []),
            septima: SeptimaSettings::fromArray($data['septima'] ?? []),
        );
    }
}
