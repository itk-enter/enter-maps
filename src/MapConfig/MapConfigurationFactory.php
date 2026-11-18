<?php

declare(strict_types=1);

namespace App\MapConfig;

use App\Entity\MapConfiguration;
use App\Renderer\LeafletConverter;
use App\Renderer\Renderer;

/**
 * Makes a MapConfiguration from the JSON of an editor, or changes one. It
 * validates the JSON and runs the converter of each renderer.
 */
final readonly class MapConfigurationFactory
{
    public function __construct(
        private MapConfigParser $parser,
        private LeafletConverter $leafletConverter,
    ) {
    }

    /**
     * @param list<Renderer> $renderers
     *
     * @throws InvalidMapConfigException if the JSON is not a valid map configuration
     */
    public function create(string $json, array $renderers): MapConfiguration
    {
        [$data, $mapJson] = $this->convert($json, $renderers);

        return new MapConfiguration($data, $renderers, $mapJson);
    }

    /**
     * Replaces the config of a map. The id stays, so the embed codes show the
     * new version.
     *
     * @throws InvalidMapConfigException if the JSON is not a valid map configuration
     */
    public function update(MapConfiguration $mapConfiguration, string $json): void
    {
        $renderers = $mapConfiguration->getRenderers();
        [$data, $mapJson] = $this->convert($json, $renderers);

        $mapConfiguration->change($data, $renderers, $mapJson);
    }

    /**
     * @param list<Renderer> $renderers
     *
     * @return array{\stdClass, array<string, array<string, mixed>>} the config and the result of each converter
     *
     * @throws InvalidMapConfigException
     */
    private function convert(string $json, array $renderers): array
    {
        $config = $this->parser->parse($json);

        $mapJson = [];
        foreach ($renderers as $renderer) {
            $mapJson[$renderer->value] = match ($renderer) {
                Renderer::Leaflet => $this->leafletConverter->convert($config),
            };
        }

        // The parser accepted the JSON, so it is an object.
        /** @var \stdClass $data */
        $data = json_decode($json, false, 512, \JSON_THROW_ON_ERROR);

        return [$data, $mapJson];
    }
}
