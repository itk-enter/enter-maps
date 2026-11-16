<?php

declare(strict_types=1);

namespace App\Tests\Unit\Renderer;

use App\Enter\Cql2FilterParser;
use App\JsonSchema\JsonSchemaValidator;
use App\MapConfig\MapConfigParser;
use App\Renderer\BaseMaps;
use App\Renderer\LeafletConverter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class LeafletConverterTest extends TestCase
{
    private MapConfigParser $parser;

    private LeafletConverter $converter;

    protected function setUp(): void
    {
        $validator = new JsonSchemaValidator(\dirname(__DIR__, 3).'/config/schema');
        $this->parser = new MapConfigParser($validator, new Cql2FilterParser($validator));

        // The same path as the route of the data proxy.
        $routes = new RouteCollection();
        $routes->add('app_data', new Route('/data/{dataSet}.geojson'));
        $this->converter = new LeafletConverter(new UrlGenerator($routes, new RequestContext()), new BaseMaps());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function fixtureProvider(): iterable
    {
        yield 'one data set' => ['toilets'];
        yield 'two data sets' => ['toilets-and-parking'];
    }

    /**
     * The expected result of each fixture is in tests/Fixtures/map-json/leaflet/.
     * If the result changes on purpose, set UPDATE_SNAPSHOTS=1 to write the
     * files again, and read the change in the diff.
     */
    #[DataProvider('fixtureProvider')]
    public function testConvertsTheFixture(string $name): void
    {
        $config = $this->parser->parse((string) file_get_contents(\dirname(__DIR__, 2).'/Fixtures/map-config/'.$name.'.json'));
        $json = json_encode($this->converter->convert($config), \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE)."\n";

        $snapshot = \dirname(__DIR__, 2).'/Fixtures/map-json/leaflet/'.$name.'.json';
        if ('1' === getenv('UPDATE_SNAPSHOTS')) {
            file_put_contents($snapshot, $json);
        }

        $this->assertJsonStringEqualsJsonFile($snapshot, $json);
    }

    public function testUsesTheDefaultsForAMinimalConfiguration(): void
    {
        $config = $this->parser->parse('{"version": 1, "title": "Bænke", "layers": [{"id": "benches", "dataSet": "osm-bench", "label": "Bænke", "colour": "#8a5a00", "row": {"title": {"path": "name"}}}]}');

        $mapJson = $this->converter->convert($config);

        $this->assertNull($mapJson['view']);
        $this->assertSame(new BaseMaps()->leaflet()[BaseMaps::LEAFLET_DEFAULT]->url, $mapJson['baseMap']['url']);
        $this->assertSame('/data/osm-bench.geojson', $mapJson['layers'][0]['url']);
        $this->assertSame('#8a5a00', $mapJson['layers'][0]['colour']);
        $this->assertNull($mapJson['report']);
    }

    public function testGivesTheDataValueOfANumericCode(): void
    {
        $config = $this->parser->parse(json_encode([
            'version' => 1,
            'title' => 'Parkering',
            'layers' => [['id' => 'parking', 'dataSet' => 'parking', 'label' => 'Parkering', 'colour' => '#1f6fb2', 'row' => ['title' => ['path' => 'name']]]],
            'filters' => [[
                'type' => 'select',
                'label' => 'Etage',
                'values' => ['1' => 'Stueetage', '2' => '1. sal'],
                'appliesTo' => [['layer' => 'parking', 'path' => 'floor', 'codes' => ['1' => 0]]],
            ]],
        ], \JSON_THROW_ON_ERROR));

        $filter = $this->converter->convert($config)['filters'][0];

        $this->assertSame([1 => 0, 2 => '2'], $filter['layers'][0]['dataValues']);
    }

    public function testTheSchemaHasTheNamesOfTheLeafletBaseMaps(): void
    {
        $schema = json_decode((string) file_get_contents(\dirname(__DIR__, 3).'/config/schema/map-config.v1.json'), true, 512, \JSON_THROW_ON_ERROR);

        $this->assertSame(array_keys(new BaseMaps()->leaflet()), $schema['properties']['leaflet']['properties']['baseMap']['enum']);
    }
}
