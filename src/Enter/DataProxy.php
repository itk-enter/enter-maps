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
    public function __construct(
        private EnterClient $enter,
    ) {
    }

    /**
     * Gets the features of a data set.
     *
     * @param string|null $filter a filter in CQL2-JSON that the entities must also match, or null for all the entities
     *
     * @return list<array<mixed>> the GeoJSON features
     *
     * @throws UnknownDataSetException   if Enter does not list the data set
     * @throws InvalidFilterException    if the filter is not valid, or uses a part of CQL2 that the proxy does not support
     * @throws EnterUnavailableException if Enter is not available
     */
    public function getFeatures(string $dataSetId, ?string $filter): array
    {
        $filter = null === $filter || '' === trim($filter) ? null : Cql2Filter::fromJson($filter);
        $dataSet = $this->enter->getDataSets()[$dataSetId] ?? null;
        if (!$dataSet instanceof DataSet) {
            throw new UnknownDataSetException(\sprintf('Enter does not list the data set "%s".', $dataSetId));
        }

        return $this->enter->getFeatures($dataSet, $filter);
    }
}
