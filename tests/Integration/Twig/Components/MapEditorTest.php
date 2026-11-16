<?php

declare(strict_types=1);

namespace App\Tests\Integration\Twig\Components;

use App\Builder\MapConfigurationImporter;
use App\Entity\MapConfiguration;
use App\Renderer\Renderer;
use App\Repository\MapConfigurationRepository;
use App\Tests\Support\Database;
use App\Tests\Support\FakeEnter;
use App\Twig\Components\MapEditor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

final class MapEditorTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    private FakeEnter $enter;

    protected function setUp(): void
    {
        self::createClient();
        Database::reset(self::getContainer()->get(EntityManagerInterface::class));
        $this->enter = new FakeEnter();
        $this->enter->dataSets = [
            ['id' => 'benches', 'title' => 'Bænke'],
            ['id' => 'merged-public-toilets', 'title' => 'Toiletter'],
        ];
        self::getContainer()->set('enter.client', $this->enter->client());

        // The first render is outside a request, and the CSRF token of the form needs a session.
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        self::getContainer()->get(RequestStack::class)->push($request);
    }

    public function testStartsANewMapWithOneLayer(): void
    {
        $crawler = $this->createLiveComponent(MapEditor::class)->render()->crawler();

        $this->assertSame('Lag 1: Nyt lag', $crawler->filter('h3')->text());
        $this->assertSame(['', 'benches', 'merged-public-toilets'], $crawler->filter('#map_layers_0_dataSet option')->each(
            static fn (Crawler $option): ?string => $option->attr('value'),
        ));
        $this->assertSame('#3661d8', $crawler->filter('#map_layers_0_colour')->attr('value'));
    }

    public function testSavesANewMapAndShowsIt(): void
    {
        $component = $this->newMapComponent();

        $component->submitForm(['map' => $this->newMap()], 'save');

        $maps = self::getContainer()->get(MapConfigurationRepository::class)->findAll();
        $this->assertCount(1, $maps);
        $this->assertSame('/builder/'.$maps[0]->getId().'/preview', $this->redirect($component));
        $config = json_decode((string) json_encode($maps[0]->getConfig()), true);
        $this->assertSame('Bænke i byen', $config['title']);
        $this->assertSame(['local' => true, 'address' => false], $config['sidebar']['search']);
        $this->assertSame([
            'id' => 'baenke',
            'dataSet' => 'benches',
            'label' => 'Bænke',
            'colour' => '#3661d8',
            'icon' => ['name' => 'bench'],
            'filter' => ['op' => '>=', 'args' => [['property' => 'seats'], 2]],
            'row' => ['title' => ['path' => 'name']],
            'detail' => [],
        ], $config['layers'][0]);
        $this->assertNotNull($maps[0]->getMapJson(Renderer::Leaflet));
    }

    public function testShowsTheEmbedCodeAfterASave(): void
    {
        $map = $this->import('toilets');
        $component = $this->createLiveComponent(MapEditor::class, ['mapId' => (string) $map->getId()]);

        $component->call('save', ['then' => MapEditor::THEN_EMBED]);

        $this->assertSame('/builder/'.$map->getId().'/embed', $this->redirect($component));
    }

    public function testShowsTheErrorsOfTheForm(): void
    {
        $component = $this->newMapComponent();
        $values = $this->newMap();
        $values['title'] = '';
        $values['viewZoom'] = '12';
        $values['layers'][0]['conditions'][0]['value'] = 'many';

        // The test client throws the exception of an invalid form. In the
        // browser, the response is 422 with the errors next to the fields.
        try {
            $component->submitForm(['map' => $values], 'save');
            $this->fail('The form is not valid.');
        } catch (UnprocessableEntityHttpException $exception) {
            $this->assertStringContainsString('map.title: Udfyld feltet.', $exception->getMessage());
            $this->assertStringContainsString('map.viewZoom: Udfyld længdegrad, breddegrad og zoom, eller lad alle tre være tomme.', $exception->getMessage());
            $this->assertStringContainsString('map.layers.0.conditions.0.value: Skriv et tal, fx 2 eller 2.5.', $exception->getMessage());
        }
        $this->assertSame([], self::getContainer()->get(MapConfigurationRepository::class)->findAll());
    }

    public function testChangesAStoredMapAndKeepsWhatTheFormDoesNotEdit(): void
    {
        $map = $this->import('toilets-and-parking');
        $component = $this->createLiveComponent(MapEditor::class, ['mapId' => (string) $map->getId()]);

        $component->submitForm(['map' => ['title' => 'Ny titel', 'layers' => [1 => ['label' => 'Parkering']]]], 'save');

        $this->assertSame('/builder/'.$map->getId().'/preview', $this->redirect($component));
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $stored = self::getContainer()->get(MapConfigurationRepository::class)->find((string) $map->getId());
        $this->assertNotNull($stored);
        $config = json_decode((string) json_encode($stored->getConfig()), true);
        $this->assertSame('Ny titel', $config['title']);
        // The id of the layer stays, so the embed codes still work.
        $this->assertSame(['toilets', 'parking'], array_column($config['layers'], 'id'));
        $this->assertSame('Parkering', $config['layers'][1]['label']);
        // The open filters and the texts of the codes are not in the form, and they stay.
        $this->assertCount(2, $config['filters']);
        $this->assertSame(['no' => 'Gratis', 'yes' => 'Betaling'], $config['layers'][1]['detail'][2]['values']);
        $this->assertSame('Ny titel', $stored->getMapJson(Renderer::Leaflet)?->title);
    }

    public function testShowsTheErrorsOfTheSchema(): void
    {
        // A config that the form cannot correct: the URL of the report link must use https.
        $config = json_decode((string) file_get_contents($this->fixture('toilets')), false);
        $config->report->url = 'http://example.dk/form';
        $map = new MapConfiguration($config, [Renderer::Leaflet], []);
        self::getContainer()->get(MapConfigurationRepository::class)->save($map);
        $component = $this->createLiveComponent(MapEditor::class, ['mapId' => (string) $map->getId()]);

        $crawler = $component->call('save')->render()->crawler();

        $this->assertSame('Kortet blev ikke gemt. Ret disse fejl.', $crawler->filter('[role="alert"] p')->text());
        $this->assertStringContainsString('/report/url', $crawler->filter('[role="alert"] li')->text());
    }

    public function testAddsItemsWithTheirDefaultValues(): void
    {
        $component = $this->createLiveComponent(MapEditor::class);

        $component->call('addCollectionItem', ['name' => 'map[layers]']);
        $component->call('addCollectionItem', ['name' => 'map[layers][1][conditions]']);
        $crawler = $component->call('addCollectionItem', ['name' => 'map[layers][1][detail]'])->render()->crawler();

        $this->assertSame('#3661d8', $crawler->filter('#map_layers_1_colour')->attr('value'));
        $this->assertSame('=', $crawler->filter('#map_layers_1_conditions_0_operator option[selected]')->attr('value'));
        $this->assertCount(1, $crawler->filter('#map_layers_1_conditions_0_value'));
        $this->assertSame('text', $crawler->filter('#map_layers_1_detail_0_format option[selected]')->attr('value'));
    }

    public function testShowsTheFieldThatTheOperatorNeeds(): void
    {
        $component = $this->createLiveComponent(MapEditor::class);
        $component->call('addCollectionItem', ['name' => 'map[layers][0][conditions]']);

        $crawler = $component->submitForm(['map' => ['layers' => [['conditions' => [['operator' => 'in']]]]]])->render()->crawler();
        $this->assertCount(1, $crawler->filter('#map_layers_0_conditions_0_values'));
        $this->assertCount(0, $crawler->filter('#map_layers_0_conditions_0_value'));

        $crawler = $component->submitForm(['map' => ['layers' => [['conditions' => [['operator' => '=', 'type' => 'boolean']]]]]])->render()->crawler();
        $this->assertCount(1, $crawler->filter('#map_layers_0_conditions_0_flag'));

        $crawler = $component->submitForm(['map' => ['layers' => [['conditions' => [['operator' => 'isNull']]]]]])->render()->crawler();
        $this->assertCount(0, $crawler->filter('#map_layers_0_conditions_0_type, #map_layers_0_conditions_0_value, #map_layers_0_conditions_0_flag'));
    }

    public function testShowsAFilterThatTheFormCannotEdit(): void
    {
        $config = json_decode((string) file_get_contents($this->fixture('toilets')), false);
        $config->layers[0]->filter = json_decode('{"op": "and", "args": [{"op": "=", "args": [{"property": "a"}, 1]}, {"op": "or", "args": [{"op": "=", "args": [{"property": "b"}, 1]}, {"op": "=", "args": [{"property": "c"}, 1]}]}]}', false);
        $map = self::getContainer()->get(MapConfigurationImporter::class)->import((string) json_encode($config));

        $crawler = $this->createLiveComponent(MapEditor::class, ['mapId' => (string) $map->getId()])->render()->crawler();

        $this->assertStringContainsString('formularen kan ikke redigere det', $crawler->filter('main, div')->first()->text());
        $this->assertStringContainsString('"op":"or"', $crawler->filter('pre code')->text());
        $this->assertCount(0, $crawler->filter('[id^="map_layers_0_conditions_"]'));
    }

    public function testSuggestsThePathsOfTheDataSet(): void
    {
        $this->enter->features['merged-public-toilets'] = [FakeEnter::feature('urn:1', ['name' => 'Dokk1', 'address' => ['streetAddress' => 'Hack Kampmanns Plads 2']])];
        $map = $this->import('toilets');

        $crawler = $this->createLiveComponent(MapEditor::class, ['mapId' => (string) $map->getId()])->render()->crawler();

        $this->assertSame(['address.streetAddress', 'name'], $crawler->filter('datalist#paths-0 option')->each(
            static fn (Crawler $option): ?string => $option->attr('value'),
        ));
        $this->assertSame('Hack Kampmanns Plads 2', $crawler->filter('datalist#paths-0 option')->first()->text());
        foreach (['title_path', 'subtitle_path', 'detail_0_path', 'conditions_0_path'] as $field) {
            $this->assertSame('paths-0', $crawler->filter('#map_layers_0_'.$field)->attr('list'), $field);
        }
        $this->assertSame('Felter i datasættet (2)', $crawler->filter('details summary')->text());
    }

    public function testTellsThatTheDataSetGaveNoPaths(): void
    {
        $map = $this->import('toilets');

        $crawler = $this->createLiveComponent(MapEditor::class, ['mapId' => (string) $map->getId()])->render()->crawler();

        $this->assertCount(0, $crawler->filter('datalist'));
        $this->assertNull($crawler->filter('#map_layers_0_title_path')->attr('list'));
        $this->assertStringContainsString('Datasættet gav ingen forslag til stier.', $crawler->text());
    }

    public function testKeepsADataSetThatEnterDoesNotList(): void
    {
        $map = $this->import('toilets-and-parking');

        $crawler = $this->createLiveComponent(MapEditor::class, ['mapId' => (string) $map->getId()])->render()->crawler();

        $this->assertSame('merged-handicap-parking-spots', $crawler->filter('#map_layers_1_dataSet option[selected]')->attr('value'));
    }

    public function testAsksForTheIdOfTheDataSetIfEnterDoesNotAnswer(): void
    {
        $this->enter->down = true;

        $crawler = $this->createLiveComponent(MapEditor::class)->render()->crawler();

        $this->assertSame('text', $crawler->filter('#map_layers_0_dataSet')->attr('type'));
        $this->assertStringContainsString('Enter svarer ikke', $crawler->filter('#map_layers_0_dataSet_help')->text());
    }

    /**
     * A new map with one condition, as after a click on "Tilføj betingelse".
     */
    private function newMapComponent(): \Symfony\UX\LiveComponent\Test\TestLiveComponent
    {
        return $this->createLiveComponent(MapEditor::class)->call('addCollectionItem', ['name' => 'map[layers][0][conditions]']);
    }

    /**
     * @return array<string, mixed>
     */
    private function newMap(): array
    {
        return [
            'title' => 'Bænke i byen',
            'search' => 'local',
            'layers' => [[
                'label' => 'Bænke',
                'dataSet' => 'benches',
                'icon' => 'bench',
                'title' => ['path' => 'name'],
                'conditions' => [['path' => 'seats', 'operator' => '>=', 'type' => 'number', 'value' => '2']],
            ]],
        ];
    }

    private function redirect(\Symfony\UX\LiveComponent\Test\TestLiveComponent $component): ?string
    {
        $response = $component->response();

        return $response->headers->get('Location') ?? $response->headers->get('X-Live-Redirect');
    }

    private function import(string $fixture): MapConfiguration
    {
        return self::getContainer()->get(MapConfigurationImporter::class)->import((string) file_get_contents($this->fixture($fixture)));
    }

    private function fixture(string $name): string
    {
        return \dirname(__DIR__, 3).'/Fixtures/map-config/'.$name.'.json';
    }
}
