<?php

declare(strict_types=1);

namespace App\Enter;

/**
 * A filter in CQL2-JSON, which the proxy changes into an NGSI-LD query.
 *
 * The map sends the filter as data, not as a query in the language of the
 * broker. The proxy makes the query and puts quotes around each text value,
 * so a filter cannot change the query. If the broker changes its query
 * language, only this class changes.
 *
 * The proxy supports the part of CQL2-JSON that has the same meaning in
 * NGSI-LD:
 *
 * - "and" and "or", with two or more arguments.
 * - "=", "<>", "<", "<=", ">" and ">=", with a property and a value.
 * - "in", with a property and a list of values.
 * - "isNull", with a property. NGSI-LD has no "not", so the proxy does not
 *   support "not".
 *
 * A value is a text, a number or true or false. A property is a dot path into
 * the GeoJSON properties. The path "address.streetAddress" becomes
 * "address[streetAddress]" in NGSI-LD, because streetAddress is a key in the
 * value of the attribute address.
 *
 * Example:
 *
 *     Cql2Filter::fromJson('{"op": "and", "args": [
 *         {"op": "=", "args": [{"property": "isAccessibleForFree"}, true]},
 *         {"op": "in", "args": [{"property": "wheelchairAccessible"}, ["yes", "limited"]]}
 *     ]}')->query;
 *     // (isAccessibleForFree==true;wheelchairAccessible=="yes","limited")
 */
final readonly class Cql2Filter
{
    public const int MAX_LENGTH = 2000;

    public const int MAX_DEPTH = 5;

    private const array COMPARISONS = ['=' => '==', '<>' => '!=', '<' => '<', '<=' => '<=', '>' => '>', '>=' => '>='];

    private const string PATH_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)*$/';

    /**
     * Text values cannot hold quotes, backslashes or control characters,
     * because the broker cannot read them inside a quoted value.
     */
    private const string TEXT_PATTERN = '/^[^"\\\\\p{Cc}]{1,200}$/u';

    /**
     * @param string $query the NGSI-LD query; "and" and "or" have parentheses
     */
    private function __construct(
        public string $query,
    ) {
    }

    /**
     * @throws InvalidFilterException
     */
    public static function fromJson(string $json): self
    {
        if (\strlen($json) > self::MAX_LENGTH) {
            throw new InvalidFilterException(\sprintf('The filter is longer than %d characters.', self::MAX_LENGTH));
        }

        try {
            $expression = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new InvalidFilterException('The filter is not valid JSON.');
        }

        return new self(self::expression($expression, 1));
    }

    /**
     * @throws InvalidFilterException
     */
    private static function expression(mixed $expression, int $depth): string
    {
        if ($depth > self::MAX_DEPTH) {
            throw new InvalidFilterException(\sprintf('The filter has more than %d levels.', self::MAX_DEPTH));
        }

        if (!\is_array($expression) || !\is_string($expression['op'] ?? null) || !\is_array($expression['args'] ?? null)
            || !array_is_list($expression['args']) || 2 !== \count($expression)) {
            throw new InvalidFilterException('Each part of the filter must be an object with only "op" and "args".');
        }

        $op = $expression['op'];
        $args = $expression['args'];

        if ('and' === $op || 'or' === $op) {
            if (\count($args) < 2) {
                throw new InvalidFilterException(\sprintf('"%s" must have two or more arguments.', $op));
            }

            $parts = array_map(static fn (mixed $arg): string => self::expression($arg, $depth + 1), $args);

            return '('.implode('and' === $op ? ';' : '|', $parts).')';
        }

        if (isset(self::COMPARISONS[$op])) {
            self::expectArguments($op, $args, 2);

            return self::property($args[0]).self::COMPARISONS[$op].self::value($args[1]);
        }

        if ('in' === $op) {
            self::expectArguments($op, $args, 2);
            if (!\is_array($args[1]) || !array_is_list($args[1]) || [] === $args[1]) {
                throw new InvalidFilterException('The second argument of "in" must be a list of values.');
            }

            return self::property($args[0]).'=='.implode(',', array_map(self::value(...), $args[1]));
        }

        if ('isNull' === $op) {
            self::expectArguments($op, $args, 1);

            return '!'.self::property($args[0]);
        }

        throw new InvalidFilterException(\sprintf('The filter does not support the operator "%s".', $op));
    }

    /**
     * @param list<mixed> $args
     *
     * @throws InvalidFilterException
     */
    private static function expectArguments(string $op, array $args, int $count): void
    {
        if (\count($args) !== $count) {
            throw new InvalidFilterException(\sprintf('"%s" must have %d arguments.', $op, $count));
        }
    }

    /**
     * @throws InvalidFilterException
     */
    private static function property(mixed $arg): string
    {
        $path = \is_array($arg) && 1 === \count($arg) ? ($arg['property'] ?? null) : null;
        if (!\is_string($path) || 1 !== preg_match(self::PATH_PATTERN, $path)) {
            throw new InvalidFilterException('The first argument must be {"property": "<path>"}, where the path holds letters, digits, "_" and ".".');
        }

        $keys = explode('.', $path);

        return array_shift($keys).implode('', array_map(static fn (string $key): string => '['.$key.']', $keys));
    }

    /**
     * @throws InvalidFilterException
     */
    private static function value(mixed $value): string
    {
        return match (true) {
            \is_bool($value) => $value ? 'true' : 'false',
            \is_int($value) => (string) $value,
            \is_float($value) => json_encode($value, \JSON_THROW_ON_ERROR),
            \is_string($value) && 1 === preg_match(self::TEXT_PATTERN, $value) => '"'.$value.'"',
            default => throw new InvalidFilterException('A value must be a number, true, false, or a text of 1 to 200 characters without quotes or backslashes.'),
        };
    }
}
