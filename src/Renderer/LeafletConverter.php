<?php

declare(strict_types=1);

namespace App\Renderer;

use App\MapConfig\Field;
use App\MapConfig\Filter;
use App\MapConfig\FilterTarget;
use App\MapConfig\Icon;
use App\MapConfig\Layer;
use App\MapConfig\MapConfig;
use App\MapConfig\Report;
use App\MapConfig\View;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Makes the settings of the Leaflet map (map_json.leaflet) from a map
 * configuration.
 *
 * The Leaflet app in the browser reads these settings. The converter does the
 * work that the browser must not do or cannot do:
 *
 * - It makes the URL of the data proxy for each layer, with the closed filter.
 * - It changes the name of the base map into the URL of the tiles.
 * - It changes the icon into markup from the list of icons, or into an image
 *   for an SVG from the editor. Without an icon, it uses the default pin.
 * - It gives the value in the data for each code of an open filter.
 * - It changes the centre to the [latitude, longitude] order of Leaflet.
 *
 * The result holds only data, so that it can be stored as JSON.
 */
final readonly class LeafletConverter
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private BaseMaps $baseMaps,
        private Icons $icons,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function convert(MapConfig $config): array
    {
        $baseMap = $this->baseMaps->leaflet()[$config->leaflet->baseMap ?? BaseMaps::LEAFLET_DEFAULT];

        return [
            'title' => $config->title,
            'description' => $config->description,
            'locale' => $config->locale,
            'view' => $config->view instanceof View ? [
                'center' => [$config->view->latitude, $config->view->longitude],
                'zoom' => $config->view->zoom,
            ] : null,
            'minZoom' => $config->minZoom,
            'baseMap' => [
                'url' => $baseMap->url,
                'attribution' => $baseMap->attribution,
                'maxZoom' => $baseMap->maxZoom,
            ],
            'sidebar' => [
                'position' => $config->sidebar->position,
                'list' => $config->sidebar->list,
                'categories' => $config->sidebar->categories,
                'localSearch' => $config->sidebar->localSearch,
                'addressSearch' => $config->sidebar->addressSearch,
            ],
            'layers' => array_map($this->layer(...), $config->layers),
            'filters' => array_map($this->filter(...), $config->filters),
            'report' => $config->report instanceof Report ? [
                'label' => $config->report->label,
                'url' => $config->report->url,
                'target' => $config->report->target,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function layer(Layer $layer): array
    {
        $parameters = ['dataSet' => $layer->dataSet];
        if (null !== $layer->filter) {
            $parameters['filter'] = json_encode($layer->filter, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
        }

        return [
            'id' => $layer->id,
            'label' => $layer->label,
            'url' => $this->urlGenerator->generate('app_data', $parameters),
            'colour' => $layer->colour,
            'icon' => $this->icon($layer->icon),
            'row' => [
                'title' => $this->field($layer->title),
                'subtitle' => $layer->subtitle instanceof Field ? $this->field($layer->subtitle) : null,
            ],
            'detail' => array_map($this->field(...), $layer->detail),
        ];
    }

    /**
     * Gives the icon as markup from the list of icons ("glyph"), or as an
     * image ("image"). The app puts the markup into the page. It shows the
     * image with an <img> element, so that a script in the SVG cannot run.
     *
     * @return array{glyph: string}|array{image: string}
     */
    private function icon(?Icon $icon): array
    {
        if (null !== $icon?->svg) {
            return ['image' => 'data:image/svg+xml;base64,'.base64_encode($icon->svg)];
        }

        return ['glyph' => $this->icons->all()[$icon->name ?? Icons::DEFAULT]];
    }

    /**
     * @return array<string, mixed>
     */
    private function field(Field $field): array
    {
        return [
            'path' => explode('.', $field->path),
            'label' => $field->label,
            'fallback' => $field->fallback,
            'format' => $field->format,
            'values' => (object) $field->values,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function filter(Filter $filter): array
    {
        return [
            'type' => $filter->type,
            'label' => $filter->label,
            'values' => $filter->values,
            'layers' => array_map(
                static function (FilterTarget $target) use ($filter): array {
                    $dataValues = [];
                    foreach (array_keys($filter->values) as $code) {
                        $dataValues[$code] = $target->valueFor($code);
                    }

                    return ['layer' => $target->layer, 'path' => explode('.', $target->path), 'dataValues' => $dataValues];
                },
                $filter->appliesTo,
            ),
        ];
    }
}
