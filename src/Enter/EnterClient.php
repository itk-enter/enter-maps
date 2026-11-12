<?php

declare(strict_types=1);

namespace App\Enter;

use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Reads the data sets and their entities from Enter.
 *
 * The broker of Enter returns at most 1000 entities for each request, so
 * getFeatures() reads the entities page by page, as the broker links them.
 */
final readonly class EnterClient
{
    public const int PAGE_SIZE = 1000;

    private const string ENTITIES_PATH = '/ngsi-ld/v1/entities';

    /**
     * Stops a broker that always links a next page. 100 pages is 100 000 entities.
     */
    public const int MAX_PAGES = 100;

    /**
     * The broker adds these properties. "location" repeats the geometry, and
     * "source" is the internal URL of the harvest. A map does not use them.
     */
    private const array REMOVED_PROPERTIES = ['location', 'source'];

    public function __construct(
        #[Target('enter.client')]
        private HttpClientInterface $client,
    ) {
    }

    /**
     * Gets the data sets that Enter lists in /sources.json. Items without an
     * ID or a title are left out.
     *
     * @return array<string, DataSet> the data sets, with the ID as key
     *
     * @throws EnterUnavailableException
     */
    public function getDataSets(): array
    {
        [$items] = $this->get('/sources.json', []);
        if (!array_is_list($items)) {
            throw new EnterUnavailableException('Enter returned a /sources.json that is not a list.');
        }

        $dataSets = [];
        foreach ($items as $item) {
            if (!\is_array($item) || !\is_string($item['id'] ?? null) || !\is_string($item['title'] ?? null)) {
                continue;
            }

            $dataSets[$item['id']] = new DataSet(
                id: $item['id'],
                title: $item['title'],
                model: \is_string($item['model'] ?? null) ? $item['model'] : null,
                contextUrl: \is_string($item['context_url'] ?? null) ? $item['context_url'] : null,
            );
        }

        return $dataSets;
    }

    /**
     * Gets all the entities of a data set as GeoJSON features.
     *
     * The method reads the pages that the broker links with rel="next" in the
     * Link header, until a page has no next page.
     *
     * @param Cql2Filter|null $filter a filter that the entities must also match
     *
     * @return list<array<mixed>>
     *
     * @throws EnterUnavailableException
     */
    public function getFeatures(DataSet $dataSet, ?Cql2Filter $filter): array
    {
        $query = [
            'q' => \sprintf('sourceId=="%s"', $dataSet->id).($filter instanceof Cql2Filter ? ';'.$filter->query : ''),
            'format' => 'simplified',
            'limit' => self::PAGE_SIZE,
        ];
        if (null !== $dataSet->model) {
            $query['type'] = $dataSet->model;
        }

        $headers = ['Accept' => 'application/geo+json'];
        if (null !== $dataSet->contextUrl) {
            $headers['Link'] = \sprintf('<%s>; rel="http://www.w3.org/ns/json-ld#context"; type="application/ld+json"', $dataSet->contextUrl);
        }

        $features = [];
        for ($page = 0; $page < self::MAX_PAGES; ++$page) {
            [$collection, $responseHeaders] = $this->get(self::ENTITIES_PATH, ['query' => $query, 'headers' => $headers]);

            $pageFeatures = $collection['features'] ?? null;
            if (!\is_array($pageFeatures) || !array_is_list($pageFeatures)) {
                throw new EnterUnavailableException(\sprintf('Enter returned entities of "%s" that are not a GeoJSON feature collection.', $dataSet->id));
            }

            foreach ($pageFeatures as $feature) {
                if (\is_array($feature)) {
                    $features[] = $this->removeProperties($feature);
                }
            }

            $query = $this->nextPage($responseHeaders['link'] ?? []);
            if (null === $query) {
                return $features;
            }
        }

        throw new EnterUnavailableException(\sprintf('Enter returned more than %d pages of entities of "%s".', self::MAX_PAGES, $dataSet->id));
    }

    /**
     * Gets the query of the next page from the Link headers of a response.
     *
     * @param list<string> $links
     *
     * @return array<mixed>|null the query, or null if there is no next page
     */
    private function nextPage(array $links): ?array
    {
        foreach ($links as $link) {
            if (1 === preg_match('/<([^>]*)>[^,]*rel="next"/', $link, $match)) {
                parse_str((string) parse_url($match[1], \PHP_URL_QUERY), $query);

                return $query;
            }
        }

        return null;
    }

    /**
     * @param array<mixed> $feature
     *
     * @return array<mixed>
     */
    private function removeProperties(array $feature): array
    {
        if (\is_array($feature['properties'] ?? null)) {
            foreach (self::REMOVED_PROPERTIES as $property) {
                unset($feature['properties'][$property]);
            }
        }

        return $feature;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array{array<mixed>, array<string, list<string>>} the decoded JSON and the headers of the response
     *
     * @throws EnterUnavailableException
     */
    private function get(string $url, array $options): array
    {
        try {
            $response = $this->client->request('GET', $url, $options);

            return [$response->toArray(), $response->getHeaders()];
        } catch (ExceptionInterface $exception) {
            throw new EnterUnavailableException(\sprintf('Enter did not return %s: %s', $url, $exception->getMessage()), $exception->getCode(), previous: $exception);
        }
    }
}
