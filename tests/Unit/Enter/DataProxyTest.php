<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enter;

use App\Enter\Cql2FilterParser;
use App\Enter\DataProxy;
use App\Enter\EnterClient;
use App\Enter\EnterUnavailableException;
use App\Enter\InvalidFilterException;
use App\Enter\UnknownDataSetException;
use App\JsonSchema\JsonSchemaValidator;
use App\Tests\Support\FakeEnter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DataProxyTest extends TestCase
{
    private FakeEnter $enter;

    private DataProxy $proxy;

    protected function setUp(): void
    {
        $this->enter = new FakeEnter();
        $this->enter->dataSets = [['id' => 'toilets', 'title' => 'Toilets']];
        $this->enter->features['toilets'] = [FakeEnter::feature('urn:1')];

        $this->proxy = new DataProxy(
            new EnterClient($this->enter->client()),
            new Cql2FilterParser(new JsonSchemaValidator(\dirname(__DIR__, 3).'/config/schema')),
        );
    }

    public function testGetFeaturesReturnsTheFeaturesOfTheDataSet(): void
    {
        $features = $this->proxy->getFeatures('toilets', null);

        $this->assertCount(1, $features);
        $this->assertSame('urn:1', $features[0]['id']);
    }

    public function testGetFeaturesAsksEnterEachTime(): void
    {
        $this->proxy->getFeatures('toilets', null);
        $this->proxy->getFeatures('toilets', null);

        // Each call: one request for /sources.json, one for the entities.
        $this->assertCount(4, $this->enter->requests);
    }

    public function testGetFeaturesFailsWhenEnterIsDown(): void
    {
        $this->enter->down = true;

        $this->expectException(EnterUnavailableException::class);

        $this->proxy->getFeatures('toilets', null);
    }

    public function testGetFeaturesFailsForAnUnknownDataSet(): void
    {
        $this->expectException(UnknownDataSetException::class);

        $this->proxy->getFeatures('benches', null);
    }

    /**
     * @return iterable<string, array{string|null}>
     */
    public static function emptyFilterProvider(): iterable
    {
        yield 'null' => [null];
        yield 'empty' => [''];
        yield 'spaces' => ['  '];
    }

    #[DataProvider('emptyFilterProvider')]
    public function testGetFeaturesWithoutAFilterGetsAllTheEntities(?string $filter): void
    {
        $this->proxy->getFeatures('toilets', $filter);

        parse_str((string) parse_url($this->enter->requests[1], \PHP_URL_QUERY), $query);
        $this->assertSame('sourceId=="toilets"', $query['q']);
    }

    public function testGetFeaturesMakesTheQueryFromTheFilter(): void
    {
        $this->proxy->getFeatures('toilets', '{"op": "=", "args": [{"property": "name"}, "Ådalen"]}');

        parse_str((string) parse_url($this->enter->requests[1], \PHP_URL_QUERY), $query);
        $this->assertSame('sourceId=="toilets";name=="Ådalen"', $query['q']);
    }

    public function testGetFeaturesRejectsAnInvalidFilterBeforeItAsksEnter(): void
    {
        try {
            $this->proxy->getFeatures('toilets', 'isAccessibleForFree==true');
            $this->fail('The proxy accepted a filter that is not CQL2-JSON.');
        } catch (InvalidFilterException) {
            $this->assertSame([], $this->enter->requests);
        }
    }
}
