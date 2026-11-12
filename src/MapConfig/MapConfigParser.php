<?php

declare(strict_types=1);

namespace App\MapConfig;

use App\Enter\Cql2FilterParser;
use App\Enter\InvalidFilterException;
use App\JsonSchema\JsonSchemaValidator;

/**
 * Makes a MapConfig from JSON, after validation against the JSON Schema of
 * its version. It also checks that the filters refer to layers and codes
 * that exist, and that the data proxy accepts each closed filter.
 *
 * Each version has its own schema file in config/schema/. Do not remove the
 * schema of an old version, because embed codes stay on other sites for a
 * long time.
 */
final readonly class MapConfigParser
{
    /**
     * The schema file of each version.
     */
    private const array SCHEMAS = [
        1 => 'map-config.v1.json',
    ];

    public function __construct(
        private JsonSchemaValidator $validator,
        private Cql2FilterParser $filterParser,
    ) {
    }

    /**
     * @throws InvalidMapConfigException if the JSON is not valid
     */
    public function parse(string $json): MapConfig
    {
        try {
            $data = json_decode($json, false, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new InvalidMapConfigException(['The configuration is not valid JSON: '.$exception->getMessage()]);
        }

        $version = $data instanceof \stdClass ? ($data->version ?? null) : null;
        $schemaFile = \is_int($version) ? (self::SCHEMAS[$version] ?? null) : null;
        if (null === $schemaFile) {
            throw new InvalidMapConfigException([\sprintf('/version: The supported versions are %s.', implode(', ', array_keys(self::SCHEMAS)))]);
        }

        $errors = $this->validator->validate($data, $schemaFile);
        if ([] !== $errors) {
            throw new InvalidMapConfigException($errors);
        }

        $data = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        $errors = [...$this->checkReferences($data), ...$this->checkFilters($data)];
        if ([] !== $errors) {
            throw new InvalidMapConfigException($errors);
        }

        return MapConfig::fromArray($data);
    }

    /**
     * Checks each closed filter with the parser of the data proxy, so that a
     * stored filter is a filter that the proxy accepts. For example, the schema
     * cannot limit the levels of a filter.
     *
     * @param array<string, mixed> $data JSON that is valid against the schema
     *
     * @return list<string>
     */
    private function checkFilters(array $data): array
    {
        $errors = [];
        foreach ($data['layers'] as $index => $layer) {
            if (!isset($layer['filter'])) {
                continue;
            }

            try {
                $this->filterParser->parse(json_encode($layer['filter'], \JSON_THROW_ON_ERROR));
            } catch (InvalidFilterException $exception) {
                $errors[] = \sprintf('/layers/%d/filter: %s', $index, $exception->getMessage());
            }
        }

        return $errors;
    }

    /**
     * Checks the rules that the schema cannot express: each layer has its own
     * id, and each filter refers to layers and codes that exist.
     *
     * @param array<string, mixed> $data JSON that is valid against the schema
     *
     * @return list<string> the errors, in the same format as the errors of the schema
     */
    private function checkReferences(array $data): array
    {
        $errors = [];
        $layerIds = [];
        foreach ($data['layers'] as $index => $layer) {
            if (isset($layerIds[$layer['id']])) {
                $errors[] = \sprintf('/layers/%d/id: The id "%s" is used by more than one layer.', $index, $layer['id']);
            }

            $layerIds[$layer['id']] = true;
        }

        foreach ($data['filters'] ?? [] as $filterIndex => $filter) {
            foreach ($filter['appliesTo'] as $targetIndex => $target) {
                $pointer = \sprintf('/filters/%d/appliesTo/%d', $filterIndex, $targetIndex);
                if (!isset($layerIds[$target['layer']])) {
                    $errors[] = \sprintf('%s/layer: There is no layer with the id "%s".', $pointer, $target['layer']);
                }

                foreach (array_keys($target['codes'] ?? []) as $code) {
                    if (!\array_key_exists($code, $filter['values'])) {
                        $errors[] = \sprintf('%s/codes/%s: The filter has no code "%s".', $pointer, $code, $code);
                    }
                }
            }
        }

        return $errors;
    }
}
