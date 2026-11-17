<?php

declare(strict_types=1);

namespace App\Builder\Form;

use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Makes the data of the map form from a config, and a config from the data.
 *
 * The form edits only a part of the config. The mapper keeps the other parts:
 * the keys of the map that the form does not know, for example "filters" and
 * "report", and the extra keys of each layer and field. A save thus does not
 * remove a part that a later version of the form can edit. The mapper reads
 * and writes these parts as objects, so that "{}" stays an object.
 *
 * A fixed filter becomes a list of conditions if it has one level: one
 * comparison, or "and" or "or" of comparisons. Another filter stays as JSON,
 * and the mapper writes it back without a change.
 *
 * Example: the filter
 *
 *     {"op": "and", "args": [
 *         {"op": "=", "args": [{"property": "isAccessibleForFree"}, true]},
 *         {"op": "in", "args": [{"property": "wheelchairAccessible"}, ["yes", "limited"]]}
 *     ]}
 *
 * gives $combine "and" and two conditions: "isAccessibleForFree", "=", type
 * "boolean", flag true; and "wheelchairAccessible", "in", type "text",
 * values "yes\nlimited".
 */
final readonly class MapFormMapper
{
    private const array MAP_KEYS = ['version', 'title', 'description', 'locale', 'view', 'zoomGate', 'loadingSpinner', 'sidebar', 'layers'];

    private const array LAYER_KEYS = ['id', 'dataSet', 'label', 'colour', 'icon', 'filter', 'row', 'detail'];

    private const array FIELD_KEYS = ['path', 'label', 'format', 'fallback'];

    public function __construct(
        private SluggerInterface $slugger,
    ) {
    }

    public function toData(?\stdClass $config): MapData
    {
        // A new map has no layers. The data set step of the form adds them.
        $data = new MapData();
        if (!$config instanceof \stdClass) {
            return $data;
        }

        $data->title = $this->string($config->title ?? null);
        $data->description = $this->string($config->description ?? null);
        $data->locale = $this->string($config->locale ?? null) ?? $data->locale;
        $data->loadingSpinner = true === ($config->loadingSpinner ?? false);

        $sidebar = $this->object($config->sidebar ?? null);
        $data->sidebarPosition = $this->string($sidebar->position ?? null) ?? $data->sidebarPosition;
        $data->showList = (bool) ($sidebar->list ?? true);
        $data->categories = (bool) ($sidebar->categories ?? false);
        $data->routeLink = (bool) ($sidebar->routeLink ?? true);
        $search = $this->object($sidebar->search ?? null);
        $data->search = match ([(bool) ($search->local ?? false), (bool) ($search->address ?? false)]) {
            [true, true] => MapData::SEARCH_BOTH,
            [true, false] => MapData::SEARCH_LOCAL,
            [false, true] => MapData::SEARCH_ADDRESS,
            default => MapData::SEARCH_NONE,
        };

        $view = $this->object($config->view ?? null);
        $center = $this->list($view->center ?? null);
        if (2 === \count($center)) {
            $data->viewLongitude = $this->float($center[0]);
            $data->viewLatitude = $this->float($center[1]);
            $data->viewZoom = $this->int($view->zoom ?? null);
        }
        $data->zoomGate = $this->int($this->object($config->zoomGate ?? null)->minZoom ?? null);

        $data->layers = array_map(fn (mixed $layer): LayerData => $this->layerToData($this->object($layer)), $this->list($config->layers ?? null));

        return $data;
    }

    /**
     * @param \stdClass|null $original the stored config, for the keys that the form does not edit
     */
    public function toConfig(MapData $data, ?\stdClass $original = null): \stdClass
    {
        $config = ['version' => 1, 'title' => (string) $data->title];
        if (null !== $data->description && '' !== trim($data->description)) {
            $config['description'] = $data->description;
        }
        $config['locale'] = $data->locale;

        if (null !== $data->viewLongitude && null !== $data->viewLatitude && null !== $data->viewZoom) {
            $config['view'] = ['center' => [$data->viewLongitude, $data->viewLatitude], 'zoom' => $data->viewZoom];
        }
        if (null !== $data->zoomGate) {
            $config['zoomGate'] = ['minZoom' => $data->zoomGate];
        }
        // The default is false, so the config has the key only if it is true.
        if ($data->loadingSpinner) {
            $config['loadingSpinner'] = true;
        }

        $config['sidebar'] = [
            'position' => $data->sidebarPosition,
            'list' => $data->showList,
            'categories' => $data->categories,
            'routeLink' => $data->routeLink,
            'search' => [
                'local' => \in_array($data->search, [MapData::SEARCH_LOCAL, MapData::SEARCH_BOTH], true),
                'address' => \in_array($data->search, [MapData::SEARCH_ADDRESS, MapData::SEARCH_BOTH], true),
            ],
        ];

        $ids = [];
        $config['layers'] = [];
        foreach ($data->layers as $layer) {
            $layer->id = $this->layerId($layer, $ids);
            $ids[] = $layer->id;
            $config['layers'][] = $this->layerToConfig($layer);
        }

        // The keys of the map that the form does not edit, for example "filters" and "report".
        $extra = $original instanceof \stdClass ? $this->extra($original, self::MAP_KEYS) : [];

        return $this->toObject($config + $extra);
    }

    private function layerToData(\stdClass $layer): LayerData
    {
        $data = new LayerData();
        $data->id = $this->string($layer->id ?? null);
        $data->dataSet = $this->string($layer->dataSet ?? null);
        $data->label = $this->string($layer->label ?? null);
        $data->colour = $this->string($layer->colour ?? null) ?? $data->colour;

        $icon = $this->object($layer->icon ?? null);
        $data->icon = $this->string($icon->name ?? null);

        $row = $this->object($layer->row ?? null);
        $data->title = $this->fieldToData($this->object($row->title ?? null));
        $data->subtitle = $this->fieldToData($this->object($row->subtitle ?? null));
        $data->detail = array_map(fn (mixed $field): FieldData => $this->fieldToData($this->object($field)), $this->list($layer->detail ?? null));

        if (isset($layer->filter)) {
            $this->filterToData($this->object($layer->filter), $data);
        }

        // An SVG icon stays in $extra. The form shows the list of icons only.
        $extra = $this->extra($layer, self::LAYER_KEYS);
        if (isset($icon->svg)) {
            $extra['icon'] = (object) ['svg' => $icon->svg];
        }
        $data->extra = $this->json((object) $extra);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function layerToConfig(LayerData $data): array
    {
        $extra = $this->extra($this->decode($data->extra), []);
        $layer = [
            'id' => $data->id,
            'dataSet' => (string) $data->dataSet,
            'label' => (string) $data->label,
            'colour' => $data->colour,
        ];

        // A name from the list replaces an SVG. Without a name, an SVG stays.
        if (null !== $data->icon && '' !== $data->icon) {
            $layer['icon'] = ['name' => $data->icon];
        } elseif (isset($extra['icon'])) {
            $layer['icon'] = $extra['icon'];
        }
        unset($extra['icon']);

        $filter = $this->filterToConfig($data);
        if (null !== $filter) {
            $layer['filter'] = $filter;
        }

        $layer['row'] = ['title' => $this->fieldToConfig($data->title)];
        if (null !== $data->subtitle->path && '' !== $data->subtitle->path) {
            $layer['row']['subtitle'] = $this->fieldToConfig($data->subtitle);
        }
        $layer['detail'] = array_map($this->fieldToConfig(...), $data->detail);

        return $layer + $extra;
    }

    private function fieldToData(\stdClass $field): FieldData
    {
        $data = new FieldData();
        $data->path = $this->string($field->path ?? null);
        $data->label = $this->string($field->label ?? null);
        $data->format = $this->string($field->format ?? null) ?? $data->format;
        $data->fallback = $this->string($field->fallback ?? null);
        $data->extra = $this->json((object) $this->extra($field, self::FIELD_KEYS));

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function fieldToConfig(FieldData $data): array
    {
        $field = ['path' => (string) $data->path];
        if (null !== $data->label && '' !== $data->label) {
            $field['label'] = $data->label;
        }
        if ('text' !== $data->format) {
            $field['format'] = $data->format;
        }
        if (null !== $data->fallback && '' !== $data->fallback) {
            $field['fallback'] = $data->fallback;
        }

        return $field + $this->extra($this->decode($data->extra), []);
    }

    private function filterToData(\stdClass $filter, LayerData $data): void
    {
        $combine = $this->string($filter->op ?? null);
        $isGroup = \in_array($combine, [LayerData::COMBINE_AND, LayerData::COMBINE_OR], true);
        $comparisons = $isGroup ? $this->list($filter->args ?? null) : [$filter];

        $conditions = [];
        foreach ($comparisons as $comparison) {
            $condition = $this->conditionToData($this->object($comparison));
            if (!$condition instanceof ConditionData) {
                $data->rawFilter = $this->json($filter);

                return;
            }
            $conditions[] = $condition;
        }

        $data->combine = $isGroup ? (string) $combine : LayerData::COMBINE_AND;
        $data->conditions = $conditions;
    }

    /**
     * @return ConditionData|null null if the comparison is a group, or if the form cannot show it
     */
    private function conditionToData(\stdClass $comparison): ?ConditionData
    {
        $operator = $this->string($comparison->op ?? null);
        $args = $this->list($comparison->args ?? null);
        $path = $this->string($this->object($args[0] ?? null)->property ?? null);
        if (null === $operator || !\in_array($operator, ConditionData::OPERATORS, true) || null === $path) {
            return null;
        }

        $condition = new ConditionData();
        $condition->path = $path;
        $condition->operator = $operator;
        if ('isNull' === $operator) {
            return $condition;
        }

        $values = 'in' === $operator ? $this->list($args[1] ?? null) : [$args[1] ?? null];
        $types = array_unique(array_map($this->type(...), $values));
        if (1 !== \count($types) || null === $types[array_key_first($types)]) {
            return null;
        }
        $condition->type = $types[array_key_first($types)];

        if ('in' === $operator) {
            $condition->values = implode("\n", array_map($this->valueToText(...), $values));
        } elseif (ConditionData::TYPE_BOOLEAN === $condition->type) {
            $condition->flag = true === $values[0];
        } else {
            $condition->value = $this->valueToText($values[0]);
        }

        return $condition;
    }

    private function filterToConfig(LayerData $data): mixed
    {
        if (null !== $data->rawFilter) {
            return $this->decode($data->rawFilter);
        }

        $comparisons = array_map($this->conditionToConfig(...), $data->conditions);

        return match (\count($comparisons)) {
            0 => null,
            1 => $comparisons[0],
            default => ['op' => $data->combine, 'args' => $comparisons],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function conditionToConfig(ConditionData $condition): array
    {
        $property = ['property' => (string) $condition->path];

        if ('isNull' === $condition->operator) {
            return ['op' => 'isNull', 'args' => [$property]];
        }

        if ('in' === $condition->operator) {
            $lines = array_filter(array_map(trim(...), explode("\n", (string) $condition->values)), static fn (string $line): bool => '' !== $line);

            return ['op' => 'in', 'args' => [$property, array_values(array_map(
                fn (string $line): string|int|float|bool => $this->textToValue($line, $condition->type),
                $lines,
            ))]];
        }

        $value = ConditionData::TYPE_BOOLEAN === $condition->type
            ? $condition->flag
            : $this->textToValue((string) $condition->value, $condition->type);

        return ['op' => $condition->operator, 'args' => [$property, $value]];
    }

    private function type(mixed $value): ?string
    {
        return match (true) {
            \is_bool($value) => ConditionData::TYPE_BOOLEAN,
            \is_int($value), \is_float($value) => ConditionData::TYPE_NUMBER,
            \is_string($value) => ConditionData::TYPE_TEXT,
            default => null,
        };
    }

    private function valueToText(mixed $value): string
    {
        return match (true) {
            \is_bool($value) => $value ? 'true' : 'false',
            \is_scalar($value) => (string) $value,
            default => '',
        };
    }

    private function textToValue(string $text, string $type): string|int|float|bool
    {
        $text = trim($text);

        return match ($type) {
            ConditionData::TYPE_NUMBER => is_numeric($text) ? $text + 0 : $text,
            ConditionData::TYPE_BOOLEAN => 'true' === $text,
            default => $text,
        };
    }

    /**
     * Gives the id of a layer. A new layer gets an id from its label, which is
     * unique on the map. A layer that has an id keeps it.
     *
     * @param list<string> $taken the ids of the layers before this one
     */
    private function layerId(LayerData $layer, array $taken): string
    {
        if (null !== $layer->id && '' !== $layer->id) {
            return $layer->id;
        }

        $base = $this->slugger->slug((string) $layer->label)->lower()->toString();
        $base = '' === $base ? 'layer' : $base;
        $id = $base;
        for ($number = 2; \in_array($id, $taken, true); ++$number) {
            $id = $base.'-'.$number;
        }

        return $id;
    }

    /**
     * @param list<string> $known
     *
     * @return array<string, mixed> the properties of the object that are not in $known, as they are
     */
    private function extra(\stdClass $object, array $known): array
    {
        return array_diff_key(get_object_vars($object), array_flip($known));
    }

    private function string(mixed $value): ?string
    {
        return \is_string($value) ? $value : null;
    }

    private function int(mixed $value): ?int
    {
        return \is_int($value) || \is_float($value) ? (int) $value : null;
    }

    private function float(mixed $value): ?float
    {
        return \is_int($value) || \is_float($value) ? (float) $value : null;
    }

    private function object(mixed $value): \stdClass
    {
        return $value instanceof \stdClass ? $value : new \stdClass();
    }

    /**
     * @return list<mixed>
     */
    private function list(mixed $value): array
    {
        return \is_array($value) ? array_values($value) : [];
    }

    /**
     * Makes a config object from arrays. The extra parts are objects, so an
     * empty object in them stays "{}".
     *
     * @param array<string, mixed> $config
     */
    private function toObject(array $config): \stdClass
    {
        /** @var \stdClass $object */
        $object = json_decode(json_encode($config, \JSON_THROW_ON_ERROR), false, 512, \JSON_THROW_ON_ERROR);

        return $object;
    }

    private function json(\stdClass $value): string
    {
        return json_encode($value, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);
    }

    private function decode(string $json): \stdClass
    {
        $value = json_decode($json, false);

        return $value instanceof \stdClass ? $value : new \stdClass();
    }
}
