<?php

declare(strict_types=1);

namespace App\Builder;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The default values of a new layer in the map form, from the parameter
 * app.layer_defaults in config/packages/map_layer_defaults.yaml. The key of
 * an entry is the NGSI-LD type of a data set, or "_default" for the other
 * data sets. All data sets of a type have the same properties, so the same
 * fields apply to each of them.
 *
 * If the entry has no path for the title, the title is the property "name"
 * of the data set, or else its first property. Then the layer has a title,
 * and the form can store it at once.
 *
 * Example: with the entry "PublicToilet: {colour: '#6b3fa0'}",
 * forDataSet('merged-public-toilets', 'PublicToilet') gives the layer
 * {"colour": "#6b3fa0", "row": {"title": {"path": "name"}}}.
 */
final readonly class LayerDefaults
{
    public const string DEFAULT_KEY = '_default';

    /**
     * @param array<string, array<string, mixed>> $defaults the entry of each type, keyed by type
     */
    public function __construct(
        #[Autowire(param: 'app.layer_defaults')]
        private array $defaults,
        private DataSetFields $dataSetFields,
    ) {
    }

    /**
     * @param string|null $type the NGSI-LD type of the data set, or null if Enter does not give it
     *
     * @return \stdClass a layer of the map configuration, without id, dataSet and label
     */
    public function forDataSet(string $dataSetId, ?string $type): \stdClass
    {
        $entry = $this->defaults[$type ?? self::DEFAULT_KEY] ?? $this->defaults[self::DEFAULT_KEY] ?? [];
        /** @var \stdClass $layer */
        $layer = json_decode(json_encode((object) $entry, \JSON_THROW_ON_ERROR), false, 512, \JSON_THROW_ON_ERROR);

        $layer->row ??= new \stdClass();
        $layer->row->title ??= new \stdClass();
        if (!isset($layer->row->title->path)) {
            $paths = array_keys($this->dataSetFields->paths($dataSetId));
            $path = \in_array('name', $paths, true) ? 'name' : ($paths[0] ?? null);
            if (null !== $path) {
                $layer->row->title->path = $path;
            }
        }

        return $layer;
    }

    /**
     * @return array<string, array<string, mixed>> the entry of each type, keyed by type
     */
    public function all(): array
    {
        return $this->defaults;
    }
}
