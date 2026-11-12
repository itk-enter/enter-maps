<?php

declare(strict_types=1);

namespace App\Tests\Unit\MapConfig;

use App\Enter\Cql2FilterParser;
use App\JsonSchema\JsonSchemaValidator;
use App\MapConfig\Field;
use App\MapConfig\Filter;
use App\MapConfig\FilterTarget;
use App\MapConfig\Icon;
use App\MapConfig\InvalidMapConfigException;
use App\MapConfig\Layer;
use App\MapConfig\LeafletSettings;
use App\MapConfig\MapConfig;
use App\MapConfig\MapConfigParser;
use App\MapConfig\Report;
use App\MapConfig\SeptimaSettings;
use App\MapConfig\Sidebar;
use App\MapConfig\SvgIconValidator;
use App\MapConfig\View;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MapConfigParserTest extends TestCase
{
    private const string MINIMAL = '{"version": 1, "title": "Bænke", "layers": [{"id": "benches", "dataSet": "osm-bench", "label": "Bænke", "colour": "#8a5a00", "row": {"title": {"path": "name"}}}]}';

    private MapConfigParser $parser;

    protected function setUp(): void
    {
        $validator = new JsonSchemaValidator(\dirname(__DIR__, 3).'/config/schema');
        $this->parser = new MapConfigParser($validator, new Cql2FilterParser($validator), new SvgIconValidator());
    }

    public function testParsesTheExampleOfThePlan(): void
    {
        $config = $this->parser->parse((string) file_get_contents(\dirname(__DIR__, 2).'/Fixtures/map-config/toilets.json'));

        $this->assertEquals(new MapConfig(
            version: 1,
            title: 'Toiletter i Aarhus',
            description: 'Offentlige toiletter, som er gratis at bruge.',
            locale: 'da-DK',
            view: new View(10.21432, 56.15355, 14.0),
            minZoom: 15.0,
            sidebar: new Sidebar('left', true, true, true, true),
            layers: [new Layer(
                id: 'toilets',
                dataSet: 'merged-public-toilets',
                label: 'Offentlige toiletter',
                colour: '#6b3fa0',
                icon: new Icon('toilet', null),
                filter: ['op' => '=', 'args' => [['property' => 'isAccessibleForFree'], true]],
                title: new Field('name', null, 'Toilet', 'text', []),
                subtitle: new Field('address.streetAddress', null, 'Adresse ikke registreret', 'text', []),
                detail: [
                    new Field('description', 'Beskrivelse', null, 'text', []),
                    new Field('openingHours', 'Åbningstider', null, 'list', []),
                ],
            )],
            filters: [new Filter('select', 'Kørestolsadgang', ['yes' => 'Ja', 'limited' => 'Delvist', 'no' => 'Nej'], [
                new FilterTarget('toilets', 'wheelchairAccessible', []),
            ])],
            report: new Report('Ret eller tilføj oplysninger', 'https://example.aarhus.dk/form?entity={id}&sources={seeAlso}', '_blank'),
            leaflet: new LeafletSettings('osm'),
            septima: new SeptimaSettings('skaermkort'),
        ), $config);
    }

    public function testUsesDefaultsForTheOptionalSettings(): void
    {
        $config = $this->parser->parse(self::MINIMAL);

        $this->assertSame('da-DK', $config->locale);
        $this->assertNull($config->view);
        $this->assertNull($config->description);
        $this->assertEquals(new LeafletSettings(null), $config->leaflet);
        $this->assertEquals(new SeptimaSettings(null), $config->septima);
        $this->assertNull($config->minZoom);
        $this->assertEquals(new Sidebar('left', true, false, false, false), $config->sidebar);
        $this->assertSame([], $config->filters);
        $this->assertNull($config->report);
        $this->assertNull($config->layers[0]->subtitle);
        $this->assertSame([], $config->layers[0]->detail);
        $this->assertEquals(new Field('name', null, null, 'text', []), $config->layers[0]->title);
    }

    public function testUsesBlankAsTheDefaultTargetOfTheReport(): void
    {
        $config = $this->parser->parse($this->withChange(['report' => ['label' => 'Ret', 'url' => 'https://example.com']]));

        $this->assertSame('_blank', $config->report?->target);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidJsonProvider(): iterable
    {
        yield 'not JSON' => ['{', 'not valid JSON'];
        yield 'not an object' => ['[]', '/version: The supported versions are 1.'];
        yield 'no version' => ['{"title": "Bænke"}', '/version: The supported versions are 1.'];
        yield 'unknown version' => ['{"version": 2}', '/version: The supported versions are 1.'];
        yield 'version as text' => ['{"version": "1"}', '/version: The supported versions are 1.'];
    }

    #[DataProvider('invalidJsonProvider')]
    public function testRejectsJsonWithoutAKnownVersion(string $json, string $expected): void
    {
        $exception = $this->parseInvalid($json);

        $this->assertStringContainsString($expected, $exception->errors[0]);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidConfigProvider(): iterable
    {
        yield 'no layers' => [['layers' => []], '/layers'];
        yield 'unknown setting' => [['script' => 'alert(1)'], '/'];
        yield 'base map URL' => [['leaflet' => ['baseMap' => 'https://tiles.example.com/{z}/{x}/{y}.png']], '/leaflet/baseMap'];
        yield 'Septima setting in the Leaflet section' => [['leaflet' => ['widget' => '3.23.0']], '/leaflet'];
        yield 'empty description' => [['description' => ''], '/description'];
        yield 'centre outside the world' => [['view' => ['center' => [200, 56], 'zoom' => 14]], '/view/center/0'];
        yield 'report without https' => [['report' => ['label' => 'Ret', 'url' => 'javascript:alert(1)']], '/report/url'];
        yield 'unknown filter type' => [['filters' => [self::filter(['type' => 'range'])]], '/filters/0/type'];
        yield 'filter without values' => [['filters' => [self::filter(['values' => []])]], '/filters/0/values'];
        yield 'filter without layers' => [['filters' => [self::filter(['appliesTo' => []])]], '/filters/0/appliesTo'];
        yield 'filter code that is an object' => [['filters' => [self::filter(['appliesTo' => [['layer' => 'benches', 'path' => 'a', 'codes' => ['x' => ['y' => 1]]]]])]], '/filters/0/appliesTo/0/codes/x'];
        yield 'layer without id' => [['layers' => [['dataSet' => 'osm-bench', 'label' => 'Bænke', 'colour' => '#8a5a00', 'row' => ['title' => ['path' => 'name']]]]], '/layers/0'];
        yield 'layer without colour' => [['layers' => [['id' => 'benches', 'dataSet' => 'osm-bench', 'label' => 'Bænke', 'row' => ['title' => ['path' => 'name']]]]], '/layers/0'];
        yield 'two layers with the same id' => [['layers' => [self::layer('benches'), self::layer('benches')]], '/layers/1/id'];
        yield 'filter for a layer that does not exist' => [['filters' => [self::filter(['appliesTo' => [['layer' => 'toilets', 'path' => 'a']]])]], '/filters/0/appliesTo/0/layer'];
        yield 'code that the filter does not have' => [['filters' => [self::filter(['appliesTo' => [['layer' => 'benches', 'path' => 'a', 'codes' => ['y' => true]]]])]], '/filters/0/appliesTo/0/codes/y'];
    }

    /**
     * @param array<string, mixed> $change
     */
    #[DataProvider('invalidConfigProvider')]
    public function testRejectsAnInvalidConfiguration(array $change, string $pointer): void
    {
        $exception = $this->parseInvalid($this->withChange($change));

        $this->assertNotSame([], preg_grep('#^'.preg_quote($pointer, '#').': #', $exception->errors), implode("\n", $exception->errors));
    }

    public function testRejectsInvalidValuesInALayer(): void
    {
        $layer = [
            'id' => 'benches',
            'dataSet' => 'osm bench',
            'label' => 'Bænke',
            'colour' => 'red',
            'filter' => ['op' => 'not', 'args' => []],
            'row' => ['title' => ['path' => 'name; drop', 'format' => 'html']],
        ];

        $exception = $this->parseInvalid($this->withChange(['layers' => [$layer]]));

        foreach (['/layers/0/dataSet', '/layers/0/colour', '/layers/0/filter/op', '/layers/0/row/title/path', '/layers/0/row/title/format'] as $pointer) {
            $this->assertNotSame([], preg_grep('#^'.preg_quote($pointer, '#').': #', $exception->errors), $pointer);
        }
    }

    public function testAcceptsAClosedFilterWithAllTheOperators(): void
    {
        $filter = ['op' => 'and', 'args' => [
            ['op' => 'or', 'args' => [
                ['op' => 'in', 'args' => [['property' => 'wheelchairAccessible'], ['yes', 'limited']]],
                ['op' => 'isNull', 'args' => [['property' => 'fee']]],
            ]],
            ['op' => '>=', 'args' => [['property' => 'address.postalCode'], 8000]],
            ['op' => '<>', 'args' => [['property' => 'name'], 'Ådalen']],
        ]];

        $config = $this->parser->parse($this->withChange(['layers' => [[...self::layer('benches'), 'filter' => $filter]]]));

        $this->assertSame($filter, $config->layers[0]->filter);
    }

    public function testAcceptsAnSvgIcon(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M0 0h1v1z"/></svg>';

        $config = $this->parser->parse($this->withChange(['layers' => [[...self::layer('benches'), 'icon' => ['svg' => $svg]]]]));

        $this->assertEquals(new Icon(null, $svg), $config->layers[0]->icon);
    }

    public function testRejectsAnSvgIconWithAScript(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><script>alert(1)</script></svg>';

        $exception = $this->parseInvalid($this->withChange(['layers' => [[...self::layer('benches'), 'icon' => ['svg' => $svg]]]]));

        $this->assertSame(['/layers/0/icon/svg: The SVG holds the element "script", which is not permitted.'], $exception->errors);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidIconProvider(): iterable
    {
        yield 'name that is not on the list' => [['name' => 'rocket']];
        yield 'name and SVG' => [['name' => 'bench', 'svg' => '<svg/>']];
        yield 'empty' => [[]];
        yield 'URL' => [['url' => 'https://example.com/icon.svg']];
    }

    /**
     * @param array<string, mixed> $icon
     */
    #[DataProvider('invalidIconProvider')]
    public function testRejectsAnInvalidIcon(array $icon): void
    {
        $exception = $this->parseInvalid($this->withChange(['layers' => [[...self::layer('benches'), 'icon' => $icon]]]));

        $this->assertNotSame([], preg_grep('#^/layers/0/icon#', $exception->errors), implode("\n", $exception->errors));
    }

    public function testRejectsAClosedFilterThatTheProxyDoesNotAccept(): void
    {
        $filter = ['op' => 'isNull', 'args' => [['property' => 'a']]];
        for ($level = 0; $level < Cql2FilterParser::MAX_DEPTH; ++$level) {
            $filter = ['op' => 'and', 'args' => [$filter, ['op' => 'isNull', 'args' => [['property' => 'b']]]]];
        }

        $exception = $this->parseInvalid($this->withChange(['layers' => [[...self::layer('benches'), 'filter' => $filter]]]));

        $this->assertSame(['/layers/0/filter: The filter has more than 5 levels.'], $exception->errors);
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function invalidClosedFilterProvider(): iterable
    {
        $isNull = ['op' => 'isNull', 'args' => [['property' => 'a']]];

        yield 'NGSI-LD text' => ['isAccessibleForFree==true', '/layers/0/filter'];
        yield 'not' => [['op' => 'not', 'args' => [$isNull]], '/layers/0/filter/op'];
        yield 'and with one filter' => [['op' => 'and', 'args' => [$isNull]], '/layers/0/filter/args'];
        yield 'value first' => [['op' => '=', 'args' => [true, ['property' => 'a']]], '/layers/0/filter/args/0'];
        yield 'quote in a text' => [['op' => '=', 'args' => [['property' => 'a'], 'x"|b==1']], '/layers/0/filter/args/1'];
        yield 'in without a list' => [['op' => 'in', 'args' => [['property' => 'a'], 'x']], '/layers/0/filter/args/1'];
        yield 'NGSI-LD in the path' => [['op' => 'isNull', 'args' => [['property' => 'a;b']]], '/layers/0/filter/args/0/property'];
    }

    #[DataProvider('invalidClosedFilterProvider')]
    public function testRejectsAnInvalidClosedFilter(mixed $filter, string $pointer): void
    {
        $exception = $this->parseInvalid($this->withChange(['layers' => [[...self::layer('benches'), 'filter' => $filter]]]));

        $this->assertNotSame([], preg_grep('#^'.preg_quote($pointer, '#').': #', $exception->errors), implode("\n", $exception->errors));
    }

    public function testParsesTwoLayersWithAFilterForBoth(): void
    {
        $config = $this->parser->parse((string) file_get_contents(\dirname(__DIR__, 2).'/Fixtures/map-config/toilets-and-parking.json'));

        $this->assertSame(['toilets', 'parking'], array_map(static fn (Layer $layer): string => $layer->id, $config->layers));
        $this->assertSame('name', $config->layers[0]->title->path);
        $this->assertSame('address.streetAddress', $config->layers[1]->title->path);

        // One control in the sidebar, with a different attribute and code in each layer.
        $this->assertEquals([
            new FilterTarget('toilets', 'isAccessibleForFree', ['free' => true]),
            new FilterTarget('parking', 'fee', ['free' => 'no']),
        ], $config->filters[0]->appliesTo);

        // A filter for one layer only. It does not change the parking spots.
        $this->assertSame(['toilets'], array_map(static fn (FilterTarget $target): string => $target->layer, $config->filters[1]->appliesTo));
    }

    /**
     * @return array<string, mixed>
     */
    private static function layer(string $id): array
    {
        return ['id' => $id, 'dataSet' => 'osm-bench', 'label' => 'Bænke', 'colour' => '#8a5a00', 'row' => ['title' => ['path' => 'name']]];
    }

    /**
     * @param array<string, mixed> $change
     *
     * @return array<string, mixed>
     */
    private static function filter(array $change): array
    {
        return [
            'type' => 'toggle',
            'label' => 'Med ryglæn',
            'values' => ['x' => 'Med ryglæn'],
            'appliesTo' => [['layer' => 'benches', 'path' => 'backrest']],
            ...$change,
        ];
    }

    /**
     * @param array<string, mixed> $change
     */
    private function withChange(array $change): string
    {
        return json_encode([...json_decode(self::MINIMAL, true, 512, \JSON_THROW_ON_ERROR), ...$change], \JSON_THROW_ON_ERROR);
    }

    private function parseInvalid(string $json): InvalidMapConfigException
    {
        try {
            $this->parser->parse($json);
        } catch (InvalidMapConfigException $exception) {
            $this->assertStringStartsWith('The map configuration is not valid: ', $exception->getMessage());

            return $exception;
        }

        $this->fail('The parser accepted an invalid configuration.');
    }
}
