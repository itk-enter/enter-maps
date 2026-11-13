<?php

declare(strict_types=1);

namespace App\Tests\Unit\MapConfig;

use App\Enter\Cql2FilterParser;
use App\JsonSchema\JsonSchemaValidator;
use App\MapConfig\InvalidMapConfigException;
use App\MapConfig\MapConfigParser;
use App\MapConfig\MapConfigurationFactory;
use App\MapConfig\SvgIconValidator;
use App\Renderer\BaseMaps;
use App\Renderer\Icons;
use App\Renderer\LeafletConverter;
use App\Renderer\Renderer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class MapConfigurationFactoryTest extends TestCase
{
    private MapConfigParser $parser;

    private LeafletConverter $converter;

    private MapConfigurationFactory $factory;

    protected function setUp(): void
    {
        $validator = new JsonSchemaValidator(\dirname(__DIR__, 3).'/config/schema');
        $this->parser = new MapConfigParser($validator, new Cql2FilterParser($validator), new SvgIconValidator());

        $routes = new RouteCollection();
        $routes->add('app_data', new Route('/data/{dataSet}.geojson'));
        $this->converter = new LeafletConverter(new UrlGenerator($routes, new RequestContext()), new BaseMaps(), new Icons());

        $this->factory = new MapConfigurationFactory($this->parser, $this->converter);
    }

    public function testStoresTheConfigurationAndTheResultOfTheConverter(): void
    {
        $json = (string) file_get_contents(\dirname(__DIR__, 2).'/Fixtures/map-config/toilets.json');

        $mapConfiguration = $this->factory->create($json, [Renderer::Leaflet]);

        $this->assertEquals(json_decode($json, false), $mapConfiguration->getConfig());
        $this->assertSame([Renderer::Leaflet], $mapConfiguration->getRenderers());
        $this->assertSame(
            json_encode($this->converter->convert($this->parser->parse($json))),
            json_encode($mapConfiguration->getMapJson(Renderer::Leaflet)),
        );
    }

    public function testRunsOnlyTheConvertersOfTheSelectedRenderers(): void
    {
        $json = (string) file_get_contents(\dirname(__DIR__, 2).'/Fixtures/map-config/toilets.json');

        $mapConfiguration = $this->factory->create($json, []);

        $this->assertNull($mapConfiguration->getMapJson(Renderer::Leaflet));
    }

    public function testRejectsAConfigurationThatIsNotValid(): void
    {
        $this->expectException(InvalidMapConfigException::class);

        $this->factory->create('{"version": 1}', [Renderer::Leaflet]);
    }
}
