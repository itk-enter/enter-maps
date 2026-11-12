<?php

declare(strict_types=1);

namespace App\Enter;

use App\JsonSchema\JsonSchemaValidator;

/**
 * Changes a filter in CQL2-JSON into an NGSI-LD query.
 *
 * The map sends the filter as data, not as a query in the language of the
 * broker. If the broker changes its query language, only this class changes.
 *
 * config/schema/cql2-filter.json defines the part of CQL2-JSON that the
 * proxy supports. The parser validates the filter against it before it makes
 * the query, and puts quotes around each text value. Thus a filter cannot
 * change the query.
 *
 * A dot path becomes the key syntax of NGSI-LD: "address.addressLocality"
 * becomes "address[addressLocality]", because addressLocality is a key in the
 * value of the attribute address.
 *
 * Example:
 *
 *     $parser->parse('{"op": "and", "args": [
 *         {"op": "=", "args": [{"property": "isAccessibleForFree"}, true]},
 *         {"op": "in", "args": [{"property": "wheelchairAccessible"}, ["yes", "limited"]]}
 *     ]}')->query;
 *     // (isAccessibleForFree==true;wheelchairAccessible=="yes","limited")
 */
final readonly class Cql2FilterParser
{
    public const int MAX_LENGTH = 2000;

    /**
     * The schema cannot limit the levels, so the parser does.
     */
    public const int MAX_DEPTH = 5;

    private const array COMPARISONS = ['=' => '==', '<>' => '!=', '<' => '<', '<=' => '<=', '>' => '>', '>=' => '>='];

    public function __construct(
        private JsonSchemaValidator $validator,
    ) {
    }

    /**
     * @throws InvalidFilterException if the filter is not valid, or uses a part of CQL2 that the proxy does not support
     */
    public function parse(string $json): Cql2Filter
    {
        if (\strlen($json) > self::MAX_LENGTH) {
            throw new InvalidFilterException(\sprintf('The filter is longer than %d characters.', self::MAX_LENGTH));
        }

        try {
            $data = json_decode($json, false, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new InvalidFilterException('The filter is not valid JSON.');
        }

        $errors = $this->validator->validate($data, 'cql2-filter.json');
        if ([] !== $errors) {
            throw new InvalidFilterException('The filter is not valid: '.implode(' ', $errors));
        }

        return new Cql2Filter($this->query(json_decode($json, true, 512, \JSON_THROW_ON_ERROR), 1));
    }

    /**
     * @param array{op: string, args: list<mixed>} $expression a filter that is valid against the schema
     *
     * @throws InvalidFilterException
     */
    private function query(array $expression, int $depth): string
    {
        if ($depth > self::MAX_DEPTH) {
            throw new InvalidFilterException(\sprintf('The filter has more than %d levels.', self::MAX_DEPTH));
        }

        $args = $expression['args'];

        return match ($expression['op']) {
            'and', 'or' => '('.implode('and' === $expression['op'] ? ';' : '|', array_map(fn (array $arg): string => $this->query($arg, $depth + 1), $args)).')',
            'in' => $this->property($args[0]).'=='.implode(',', array_map($this->value(...), $args[1])),
            'isNull' => '!'.$this->property($args[0]),
            default => $this->property($args[0]).self::COMPARISONS[$expression['op']].$this->value($args[1]),
        };
    }

    /**
     * @param array{property: string} $arg
     */
    private function property(array $arg): string
    {
        $keys = explode('.', $arg['property']);

        return array_shift($keys).implode('', array_map(static fn (string $key): string => '['.$key.']', $keys));
    }

    private function value(string|int|float|bool $value): string
    {
        return match (true) {
            \is_bool($value) => $value ? 'true' : 'false',
            \is_string($value) => '"'.$value.'"',
            default => json_encode($value, \JSON_THROW_ON_ERROR),
        };
    }
}
