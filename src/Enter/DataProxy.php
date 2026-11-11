<?php

declare(strict_types=1);

namespace App\Enter;

/**
 * Gives the data of Enter to the maps.
 *
 * The proxy has no cache. Each call sends requests to Enter: one for
 * /sources.json, and one for each page of entities.
 */
final readonly class DataProxy
{
    public const int MAX_WHERE_LENGTH = 500;

    /**
     * The characters of an NGSI-LD query: letters, digits, the operators and
     * the characters in quoted values.
     */
    private const string WHERE_PATTERN = '/^[\p{L}\p{N} _.:@#\-="\'<>!~;|()\[\],\/+*%]+$/u';

    public function __construct(
        private EnterClient $enter,
    ) {
    }

    /**
     * Gets the features of a data set.
     *
     * @param string|null $where an NGSI-LD query that the entities must also match, or null for all the entities
     *
     * @return list<array<mixed>> the GeoJSON features
     *
     * @throws UnknownDataSetException   if Enter does not list the data set
     * @throws InvalidWhereException     if $where is too long or holds characters that are not permitted
     * @throws EnterUnavailableException if Enter is not available
     */
    public function getFeatures(string $dataSetId, ?string $where): array
    {
        $where = $this->validateWhere($where);
        $dataSet = $this->enter->getDataSets()[$dataSetId] ?? null;
        if (!$dataSet instanceof DataSet) {
            throw new UnknownDataSetException(\sprintf('Enter does not list the data set "%s".', $dataSetId));
        }

        return $this->enter->getFeatures($dataSet, $where);
    }

    /**
     * @return string|null the filter, or null if there is no filter
     *
     * @throws InvalidWhereException
     */
    private function validateWhere(?string $where): ?string
    {
        $where = null === $where ? '' : trim($where);
        if ('' === $where) {
            return null;
        }

        if (mb_strlen($where) > self::MAX_WHERE_LENGTH) {
            throw new InvalidWhereException(\sprintf('The "where" filter is longer than %d characters.', self::MAX_WHERE_LENGTH));
        }

        if (1 !== preg_match(self::WHERE_PATTERN, $where)) {
            throw new InvalidWhereException('The "where" filter holds characters that are not permitted.');
        }

        return $where;
    }
}
