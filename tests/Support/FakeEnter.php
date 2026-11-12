<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * A fake Enter for tests. It answers /sources.json and the entities of each
 * data set from arrays, and records the requests.
 *
 * Example:
 *
 *     $enter = new FakeEnter();
 *     $enter->dataSets = [['id' => 'toilets', 'title' => 'Toilets', 'model' => 'PublicToilet', 'context_url' => 'https://example.com/context.jsonld']];
 *     $enter->features['toilets'] = [FakeEnter::feature('urn:1')];
 *     $client = $enter->client();
 */
final class FakeEnter
{
    /**
     * The items of /sources.json.
     *
     * @var array<mixed>
     */
    public array $dataSets = [];

    /**
     * The features of each data set, with the data set ID as key.
     *
     * @var array<string, list<array<mixed>>>
     */
    public array $features = [];

    /**
     * If true, each request fails with HTTP 500.
     */
    public bool $down = false;

    /**
     * The URLs of the requests, in the order that they came.
     *
     * @var list<string>
     */
    public array $requests = [];

    /**
     * The options of the requests, in the order that they came.
     *
     * @var list<array<mixed>>
     */
    public array $options = [];

    public function client(): MockHttpClient
    {
        return new MockHttpClient($this->respond(...), 'http://enter.example');
    }

    /**
     * Makes a GeoJSON feature with the properties that the broker adds.
     *
     * @param array<string, mixed> $properties
     *
     * @return array<mixed>
     */
    public static function feature(string $id, array $properties = []): array
    {
        return [
            'type' => 'Feature',
            'id' => $id,
            'geometry' => ['type' => 'Point', 'coordinates' => [10.2, 56.15]],
            'properties' => [...$properties, 'location' => ['type' => 'Point'], 'source' => 'https://harvest.example'],
        ];
    }

    /**
     * @param array<mixed> $options
     */
    private function respond(string $method, string $url, array $options): MockResponse
    {
        $this->requests[] = $url;
        $this->options[] = $options;

        if ($this->down) {
            return new MockResponse('', ['http_code' => 500]);
        }

        $path = parse_url($url, \PHP_URL_PATH);
        if ('/sources.json' === $path) {
            return new MockResponse(json_encode($this->dataSets, \JSON_THROW_ON_ERROR));
        }

        if ('/ngsi-ld/v1/entities' === $path) {
            parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);
            $dataSetId = preg_match('/^sourceId=="([^"]+)"/', (string) ($query['q'] ?? ''), $matches) ? $matches[1] : '';
            $limit = (int) ($query['limit'] ?? 0);
            $offset = (int) ($query['offset'] ?? 0);
            $features = $this->features[$dataSetId] ?? [];
            $page = \array_slice($features, $offset, $limit);

            // The broker links the next page in the Link header, as Scorpio does.
            $headers = [];
            if ($offset + $limit < \count($features)) {
                $next = '/ngsi-ld/v1/entities?'.http_build_query([...$query, 'offset' => $offset + $limit]);
                $headers['Link'] = \sprintf('<%s>;rel="next";type="application/ld+json"', $next);
            }

            return new MockResponse(
                json_encode(['type' => 'FeatureCollection', 'features' => $page], \JSON_THROW_ON_ERROR),
                ['response_headers' => $headers],
            );
        }

        return new MockResponse('', ['http_code' => 404]);
    }
}
