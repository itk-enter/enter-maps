<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enter;

use App\Enter\Cql2Filter;
use App\Enter\DataSet;
use App\Enter\EnterClient;
use App\Enter\EnterUnavailableException;
use App\Tests\Support\FakeEnter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class EnterClientTest extends TestCase
{
    public function testGetDataSetsReadsSourcesJson(): void
    {
        $enter = new FakeEnter();
        $enter->dataSets = [
            ['id' => 'toilets', 'title' => 'Toilets', 'model' => 'PublicToilet', 'context_url' => 'https://example.com/context.jsonld'],
            ['id' => 'benches', 'title' => 'Benches'],
            ['id' => 'no-title'],
            'not an object',
        ];

        $dataSets = new EnterClient($enter->client())->getDataSets();

        $this->assertEquals([
            'toilets' => new DataSet('toilets', 'Toilets', 'PublicToilet', 'https://example.com/context.jsonld'),
            'benches' => new DataSet('benches', 'Benches', null, null),
        ], $dataSets);
    }

    public function testGetDataSetsFailsIfSourcesJsonIsNotAList(): void
    {
        $client = new MockHttpClient(new MockResponse('{"id": "toilets"}'), 'http://enter.example');

        $this->expectException(EnterUnavailableException::class);
        $this->expectExceptionMessageIsOrContains('not a list');

        new EnterClient($client)->getDataSets();
    }

    public function testGetDataSetsFailsIfEnterIsDown(): void
    {
        $enter = new FakeEnter();
        $enter->down = true;

        $this->expectException(EnterUnavailableException::class);
        $this->expectExceptionMessageIsOrContains('Enter did not return /sources.json');

        new EnterClient($enter->client())->getDataSets();
    }

    public function testGetFeaturesSendsTheQueryOfTheDataSet(): void
    {
        $enter = new FakeEnter();
        $enter->features['toilets'] = [FakeEnter::feature('urn:1')];
        $dataSet = new DataSet('toilets', 'Toilets', 'PublicToilet', 'https://example.com/context.jsonld');

        new EnterClient($enter->client())->getFeatures($dataSet, null);

        $this->assertCount(1, $enter->requests);
        parse_str((string) parse_url($enter->requests[0], \PHP_URL_QUERY), $query);
        $this->assertSame([
            'q' => 'sourceId=="toilets"',
            'format' => 'simplified',
            'limit' => '1000',
            'type' => 'PublicToilet',
        ], $query);
        $this->assertContains('Accept: application/geo+json', $enter->options[0]['headers']);
        $this->assertContains('Link: <https://example.com/context.jsonld>; rel="http://www.w3.org/ns/json-ld#context"; type="application/ld+json"', $enter->options[0]['headers']);
    }

    public function testGetFeaturesWithoutModelOrContextSendsNoTypeOrLink(): void
    {
        $enter = new FakeEnter();

        new EnterClient($enter->client())->getFeatures(new DataSet('benches', 'Benches', null, null), null);

        parse_str((string) parse_url($enter->requests[0], \PHP_URL_QUERY), $query);
        $this->assertArrayNotHasKey('type', $query);
        $this->assertSame([], preg_grep('/^Link:/', $enter->options[0]['headers']));
    }

    public function testGetFeaturesAddsTheFilter(): void
    {
        $enter = new FakeEnter();
        $filter = new Cql2Filter('(a==1|b==2)');

        new EnterClient($enter->client())->getFeatures(new DataSet('toilets', 'Toilets', null, null), $filter);

        parse_str((string) parse_url($enter->requests[0], \PHP_URL_QUERY), $query);
        $this->assertSame('sourceId=="toilets";(a==1|b==2)', $query['q']);
    }

    public function testGetFeaturesFollowsTheNextLinkAndRemovesBrokerProperties(): void
    {
        $enter = new FakeEnter();
        $enter->features['toilets'] = array_map(
            static fn (int $i): array => FakeEnter::feature('urn:'.$i, ['name' => 'Toilet '.$i]),
            range(1, EnterClient::PAGE_SIZE + 1),
        );

        $features = new EnterClient($enter->client())->getFeatures(new DataSet('toilets', 'Toilets', 'PublicToilet', 'https://example.com/context.jsonld'), null);

        $this->assertCount(2, $enter->requests);
        parse_str((string) parse_url($enter->requests[1], \PHP_URL_QUERY), $query);
        $this->assertSame((string) EnterClient::PAGE_SIZE, $query['offset']);
        $this->assertSame('sourceId=="toilets"', $query['q']);
        $this->assertContains('Link: <https://example.com/context.jsonld>; rel="http://www.w3.org/ns/json-ld#context"; type="application/ld+json"', $enter->options[1]['headers']);
        $this->assertCount(EnterClient::PAGE_SIZE + 1, $features);
        $this->assertSame(['name' => 'Toilet 1'], $features[0]['properties']);
    }

    public function testGetFeaturesStopsWhenThereIsNoNextLink(): void
    {
        $fullPage = json_encode([
            'type' => 'FeatureCollection',
            'features' => array_fill(0, EnterClient::PAGE_SIZE, ['type' => 'Feature']),
        ], \JSON_THROW_ON_ERROR);
        $client = new MockHttpClient([new MockResponse($fullPage)], 'http://enter.example');

        $features = new EnterClient($client)->getFeatures(new DataSet('toilets', 'Toilets', null, null), null);

        $this->assertCount(EnterClient::PAGE_SIZE, $features);
    }

    public function testGetFeaturesFollowsTheNextLinkAfterAShortPage(): void
    {
        $page = json_encode(['type' => 'FeatureCollection', 'features' => [['type' => 'Feature']]], \JSON_THROW_ON_ERROR);
        $client = new MockHttpClient([
            new MockResponse($page, ['response_headers' => ['Link' => '</ngsi-ld/v1/entities?q=x&offset=1>;rel="next";type="application/ld+json"']]),
            new MockResponse($page),
        ], 'http://enter.example');

        $features = new EnterClient($client)->getFeatures(new DataSet('toilets', 'Toilets', null, null), null);

        $this->assertCount(2, $features);
    }

    public function testGetFeaturesKeepsFeaturesWithoutPropertiesAndSkipsItemsThatAreNotFeatures(): void
    {
        $client = new MockHttpClient(
            new MockResponse('{"type": "FeatureCollection", "features": [{"type": "Feature", "id": "urn:1"}, "not a feature"]}'),
            'http://enter.example',
        );

        $features = new EnterClient($client)->getFeatures(new DataSet('toilets', 'Toilets', null, null), null);

        $this->assertSame([['type' => 'Feature', 'id' => 'urn:1']], $features);
    }

    public function testGetFeaturesFailsIfTheResponseIsNotAFeatureCollection(): void
    {
        $client = new MockHttpClient(new MockResponse('{"error": "something"}'), 'http://enter.example');

        $this->expectException(EnterUnavailableException::class);
        $this->expectExceptionMessageIsOrContains('not a GeoJSON feature collection');

        new EnterClient($client)->getFeatures(new DataSet('toilets', 'Toilets', null, null), null);
    }

    public function testGetFeaturesStopsAfterMaxPages(): void
    {
        $page = json_encode(['type' => 'FeatureCollection', 'features' => []], \JSON_THROW_ON_ERROR);
        $client = new MockHttpClient(
            static fn (): MockResponse => new MockResponse($page, ['response_headers' => ['Link' => '</ngsi-ld/v1/entities?offset=1>; rel="next"']]),
            'http://enter.example',
        );

        $this->expectException(EnterUnavailableException::class);
        $this->expectExceptionMessageIsOrContains('more than '.EnterClient::MAX_PAGES.' pages');

        new EnterClient($client)->getFeatures(new DataSet('toilets', 'Toilets', null, null), null);
    }
}
