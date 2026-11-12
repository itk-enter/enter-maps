<?php

declare(strict_types=1);

namespace App\Tests\Unit\MapConfig;

use App\MapConfig\Field;
use App\MapConfig\Filter;
use App\MapConfig\InvalidMapConfigException;
use App\MapConfig\Layer;
use App\MapConfig\LeafletSettings;
use App\MapConfig\MapConfig;
use App\MapConfig\MapConfigParser;
use App\MapConfig\Report;
use App\MapConfig\SeptimaSettings;
use App\MapConfig\Sidebar;
use App\MapConfig\View;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MapConfigParserTest extends TestCase
{
    private const string MINIMAL = '{"version": 1, "title": "Bænke", "layers": [{"dataSet": "osm-bench", "label": "Bænke", "row": {"title": {"path": "name"}}}]}';

    private MapConfigParser $parser;

    protected function setUp(): void
    {
        $this->parser = new MapConfigParser(\dirname(__DIR__, 3).'/config/schema');
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
                dataSet: 'merged-public-toilets',
                label: 'Offentlige toiletter',
                colour: '#6b3fa0',
                icon: 'toilet',
                where: 'isAccessibleForFree==true',
                title: new Field('name', null, 'Toilet', 'text', []),
                subtitle: new Field('address.streetAddress', null, 'Adresse ikke registreret', 'text', []),
                detail: [
                    new Field('description', 'Beskrivelse', null, 'text', []),
                    new Field('openingHours', 'Åbningstider', null, 'list', []),
                ],
            )],
            filters: [new Filter('wheelchairAccessible', 'select', 'Kørestolsadgang', ['yes' => 'Ja', 'limited' => 'Delvist', 'no' => 'Nej'])],
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
        yield 'unknown filter type' => [['filters' => [['path' => 'a', 'type' => 'range', 'label' => 'A', 'values' => ['x' => 'X']]]], '/filters/0/type'];
        yield 'filter without values' => [['filters' => [['path' => 'a', 'type' => 'select', 'label' => 'A', 'values' => []]]], '/filters/0/values'];
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
            'dataSet' => 'osm bench',
            'label' => 'Bænke',
            'colour' => 'red',
            'where' => str_repeat('a', 501),
            'row' => ['title' => ['path' => 'name; drop', 'format' => 'html']],
        ];

        $exception = $this->parseInvalid($this->withChange(['layers' => [$layer]]));

        foreach (['/layers/0/dataSet', '/layers/0/colour', '/layers/0/where', '/layers/0/row/title/path', '/layers/0/row/title/format'] as $pointer) {
            $this->assertNotSame([], preg_grep('#^'.preg_quote($pointer, '#').': #', $exception->errors), $pointer);
        }
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
