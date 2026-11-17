<?php

declare(strict_types=1);

namespace App\Tests\Unit\Builder\Form;

use App\Builder\Form\ConditionData;
use App\Builder\Form\FieldData;
use App\Builder\Form\LayerData;
use App\Builder\Form\MapData;
use App\Builder\Form\MapFormMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\String\Slugger\AsciiSlugger;

final class MapFormMapperTest extends TestCase
{
    private MapFormMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new MapFormMapper(new AsciiSlugger());
    }

    public function testGivesNoLayersForANewMap(): void
    {
        $data = $this->mapper->toData(null);

        $this->assertNull($data->title);
        $this->assertSame('da-DK', $data->locale);
        $this->assertSame(MapData::SEARCH_NONE, $data->search);
        $this->assertSame([], $data->layers);
    }

    public function testReadsTheFieldsOfTheForm(): void
    {
        $data = $this->mapper->toData($this->fixture('toilets'));

        $this->assertSame('Toiletter i Aarhus', $data->title);
        $this->assertSame(MapData::SEARCH_BOTH, $data->search);
        $this->assertSame([10.21432, 56.15355, 14], [$data->viewLongitude, $data->viewLatitude, $data->viewZoom]);
        $this->assertSame(15, $data->zoomGate);

        $layer = $data->layers[0];
        $this->assertSame(['toilets', 'merged-public-toilets', 'Offentlige toiletter', '#6b3fa0', 'toilet'], [$layer->id, $layer->dataSet, $layer->label, $layer->colour, $layer->icon]);
        $this->assertSame(['name', 'Toilet'], [$layer->title->path, $layer->title->fallback]);
        $this->assertSame('address.streetAddress', $layer->subtitle->path);
        $this->assertSame(['description', 'openingHours'], array_map(static fn (FieldData $field): ?string => $field->path, $layer->detail));
        $this->assertSame('list', $layer->detail[1]->format);

        $this->assertCount(1, $layer->conditions);
        $condition = $layer->conditions[0];
        $this->assertSame(['isAccessibleForFree', '=', ConditionData::TYPE_BOOLEAN, true], [$condition->path, $condition->operator, $condition->type, $condition->flag]);
        $this->assertNull($layer->rawFilter);
    }

    /**
     * The form keeps the parts that it does not edit, and adds only the
     * defaults that it always writes.
     *
     * @param array<string, mixed> $defaults
     */
    #[DataProvider('fixtures')]
    public function testKeepsAConfigWithoutAChange(string $fixture, array $defaults): void
    {
        $original = $this->fixture($fixture);

        $config = $this->mapper->toConfig($this->mapper->toData($original), $original);

        $this->assertEquals(
            array_replace_recursive($this->decode($original), $defaults),
            $this->decode($config),
        );
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>}>
     */
    public static function fixtures(): iterable
    {
        yield 'toilets' => ['toilets', ['sidebar' => ['routeLink' => true]]];
        yield 'toilets and parking' => ['toilets-and-parking', [
            'locale' => 'da-DK',
            'sidebar' => ['position' => 'left', 'routeLink' => true, 'search' => ['local' => false, 'address' => false]],
        ]];
    }

    /**
     * The one search field of the map comes from two settings.
     */
    #[DataProvider('searches')]
    public function testReadsAndWritesTheSearch(bool $local, bool $address, string $search): void
    {
        $original = $this->config([], ['sidebar' => ['search' => ['local' => $local, 'address' => $address]]]);

        $data = $this->mapper->toData($original);

        $this->assertSame($search, $data->search);
        $this->assertSame(['local' => $local, 'address' => $address], $this->decode($this->mapper->toConfig($data, $original))['sidebar']['search']);
    }

    /**
     * @return iterable<string, array{bool, bool, string}>
     */
    public static function searches(): iterable
    {
        yield 'none' => [false, false, MapData::SEARCH_NONE];
        yield 'local' => [true, false, MapData::SEARCH_LOCAL];
        yield 'address' => [false, true, MapData::SEARCH_ADDRESS];
        yield 'both' => [true, true, MapData::SEARCH_BOTH];
    }

    public function testKeepsEmptyObjects(): void
    {
        $original = $this->config(['detail' => [['path' => 'fee', 'values' => new \stdClass()]]], ['report' => (object) []]);

        $json = json_encode($this->mapper->toConfig($this->mapper->toData($original), $original), \JSON_THROW_ON_ERROR);

        $this->assertStringContainsString('"values":{}', $json);
        $this->assertStringContainsString('"report":{}', $json);
    }

    public function testKeepsAFilterWithMoreThanOneLevel(): void
    {
        $filter = ['op' => 'and', 'args' => [
            ['op' => '=', 'args' => [['property' => 'a'], true]],
            ['op' => 'or', 'args' => [['op' => '=', 'args' => [['property' => 'b'], 1]], ['op' => 'isNull', 'args' => [['property' => 'c']]]]],
        ]];
        $original = $this->config(['filter' => $filter]);

        $data = $this->mapper->toData($original);

        $this->assertSame([], $data->layers[0]->conditions);
        $this->assertNotNull($data->layers[0]->rawFilter);
        $this->assertEquals($filter, $this->decode($this->mapper->toConfig($data, $original))['layers'][0]['filter']);
    }

    /**
     * @param array<string, mixed> $filter
     */
    #[DataProvider('filters')]
    public function testReadsAndWritesAFixedFilter(array $filter): void
    {
        $original = $this->config(['filter' => $filter]);

        $data = $this->mapper->toData($original);

        $this->assertNull($data->layers[0]->rawFilter);
        $this->assertSame($filter, $this->decode($this->mapper->toConfig($data, $original))['layers'][0]['filter']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function filters(): iterable
    {
        yield 'a text' => [['op' => '=', 'args' => [['property' => 'address.addressLocality'], 'Aarhus C']]];
        yield 'a number' => [['op' => '>=', 'args' => [['property' => 'totalSpotNumber'], 2]]];
        yield 'a decimal number' => [['op' => '<', 'args' => [['property' => 'height'], 2.5]]];
        yield 'false' => [['op' => '<>', 'args' => [['property' => 'closed'], false]]];
        yield 'a list' => [['op' => 'in', 'args' => [['property' => 'wheelchairAccessible'], ['yes', 'limited']]]];
        yield 'a missing value' => [['op' => 'isNull', 'args' => [['property' => 'closed']]]];
        yield 'or' => [['op' => 'or', 'args' => [
            ['op' => '=', 'args' => [['property' => 'a'], 'x']],
            ['op' => 'in', 'args' => [['property' => 'b'], [1, 2]]],
        ]]];
    }

    /**
     * @param array<string, mixed> $filter
     */
    #[DataProvider('filtersTheFormCannotShow')]
    public function testKeepsAFilterThatTheFormCannotShow(array $filter): void
    {
        $data = $this->mapper->toData($this->config(['filter' => $filter]));

        $this->assertSame([], $data->layers[0]->conditions);
        $this->assertSame($filter, json_decode((string) $data->layers[0]->rawFilter, true));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function filtersTheFormCannotShow(): iterable
    {
        yield 'an unknown operator' => [['op' => 'like', 'args' => [['property' => 'a'], 'x']]];
        yield 'no property' => [['op' => '=', 'args' => ['a', 'x']]];
        yield 'a list with two types' => [['op' => 'in', 'args' => [['property' => 'a'], ['x', 1]]]];
        yield 'an object as the value' => [['op' => '=', 'args' => [['property' => 'a'], ['x' => 1]]]];
    }

    public function testWritesTheConditionsOfTheForm(): void
    {
        $data = $this->mapper->toData(null);
        $data->layers = [new LayerData()];
        $layer = $data->layers[0];
        $layer->combine = LayerData::COMBINE_OR;
        $layer->conditions = [
            $this->condition('a', '=', ConditionData::TYPE_NUMBER, value: ' 2 '),
            $this->condition('b', '=', ConditionData::TYPE_NUMBER, value: 'many'),
            $this->condition('c', '<>', ConditionData::TYPE_BOOLEAN, flag: false),
            $this->condition('d', 'in', ConditionData::TYPE_TEXT, values: "yes\n\n limited \n"),
            $this->condition('e', 'in', ConditionData::TYPE_BOOLEAN, values: "true\nfalse"),
        ];

        $filter = $this->decode($this->mapper->toConfig($data))['layers'][0]['filter'];

        $this->assertSame(['op' => 'or', 'args' => [
            ['op' => '=', 'args' => [['property' => 'a'], 2]],
            // A value that is not a number stays a text. The form shows an error for it.
            ['op' => '=', 'args' => [['property' => 'b'], 'many']],
            ['op' => '<>', 'args' => [['property' => 'c'], false]],
            ['op' => 'in', 'args' => [['property' => 'd'], ['yes', 'limited']]],
            ['op' => 'in', 'args' => [['property' => 'e'], [true, false]]],
        ]], $filter);
    }

    public function testWritesTheMapFromTheForm(): void
    {
        $data = $this->mapper->toData(null);
        $data->title = 'Bænke';
        $data->description = ' ';
        $data->locale = 'en-GB';
        $data->sidebarPosition = 'right';
        $data->showList = false;
        $data->categories = true;
        $data->routeLink = false;
        $data->search = MapData::SEARCH_ADDRESS;
        $data->viewLongitude = 10.2;
        $data->viewLatitude = 56.1;
        $data->viewZoom = null;
        $data->layers = [new LayerData()];
        $layer = $data->layers[0];
        $layer->dataSet = 'benches';
        $layer->label = 'Bænke';
        $layer->title->path = 'name';
        $layer->subtitle->label = 'Not used, because the subtitle has no path';
        $layer->detail = [new FieldData()];
        $layer->detail[0]->path = 'material';
        $layer->detail[0]->format = 'list';
        $second = new LayerData();
        $second->label = 'Bænke';
        $third = new LayerData();
        $third->label = '!!!';
        $data->layers[] = $second;
        $data->layers[] = $third;

        $config = $this->decode($this->mapper->toConfig($data));

        $this->assertSame(['version' => 1, 'title' => 'Bænke', 'locale' => 'en-GB'], array_slice($config, 0, 3));
        // A view needs all three fields.
        $this->assertArrayNotHasKey('view', $config);
        $this->assertArrayNotHasKey('zoomGate', $config);
        // The spinner is off by default, so the config has no key for it.
        $this->assertArrayNotHasKey('loadingSpinner', $config);
        $this->assertSame(['position' => 'right', 'list' => false, 'categories' => true, 'routeLink' => false, 'search' => ['local' => false, 'address' => true]], $config['sidebar']);
        $this->assertSame([
            'id' => 'baenke',
            'dataSet' => 'benches',
            'label' => 'Bænke',
            'colour' => '#3661d8',
            'row' => ['title' => ['path' => 'name']],
            'detail' => [['path' => 'material', 'format' => 'list']],
        ], $config['layers'][0]);
        // A new layer gets a unique id from its label.
        $this->assertSame(['baenke', 'baenke-2', 'layer'], array_column($config['layers'], 'id'));
        $this->assertSame('baenke-2', $second->id);
    }

    public function testKeepsTheIdOfALayer(): void
    {
        $original = $this->config([]);
        $data = $this->mapper->toData($original);
        $data->layers[0]->label = 'A new label';

        $this->assertSame('benches', $this->decode($this->mapper->toConfig($data, $original))['layers'][0]['id']);
    }

    public function testReplacesAnSvgIconOnlyIfTheEditorSelectsAnIcon(): void
    {
        $original = $this->config(['icon' => ['svg' => '<svg/>']]);
        $data = $this->mapper->toData($original);

        $this->assertNull($data->layers[0]->icon);
        $this->assertSame(['svg' => '<svg/>'], $this->decode($this->mapper->toConfig($data, $original))['layers'][0]['icon']);

        $data->layers[0]->icon = 'bench';
        $this->assertSame(['name' => 'bench'], $this->decode($this->mapper->toConfig($data, $original))['layers'][0]['icon']);
    }

    private function condition(string $path, string $operator, string $type, ?string $value = null, bool $flag = true, ?string $values = null): ConditionData
    {
        $condition = new ConditionData();
        $condition->path = $path;
        $condition->operator = $operator;
        $condition->type = $type;
        $condition->value = $value;
        $condition->flag = $flag;
        $condition->values = $values;

        return $condition;
    }

    /**
     * A config with one layer.
     *
     * @param array<string, mixed> $layer the keys to add to the layer
     * @param array<string, mixed> $map   the keys to add to the map
     */
    private function config(array $layer, array $map = []): \stdClass
    {
        $config = [
            'version' => 1,
            'title' => 'Bænke',
            'layers' => [[
                'id' => 'benches',
                'dataSet' => 'benches',
                'label' => 'Bænke',
                'colour' => '#000000',
                'row' => ['title' => ['path' => 'name']],
                ...$layer,
            ]],
            ...$map,
        ];

        /** @var \stdClass $object */
        $object = json_decode(json_encode($config, \JSON_THROW_ON_ERROR), false, 512, \JSON_THROW_ON_ERROR);

        return $object;
    }

    private function fixture(string $name): \stdClass
    {
        /** @var \stdClass $config */
        $config = json_decode((string) file_get_contents(\dirname(__DIR__, 3).'/Fixtures/map-config/'.$name.'.json'), false, 512, \JSON_THROW_ON_ERROR);

        return $config;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(\stdClass $config): array
    {
        /** @var array<string, mixed> $array */
        $array = json_decode(json_encode($config, \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);

        return $array;
    }
}
