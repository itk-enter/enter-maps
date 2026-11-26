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
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
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
            ['id' => 'merged-public-toilets', 'title' => 'Toiletter', 'model' => 'PublicToilet'],
        ];
        self::getContainer()->set('enter.client', $this->enter->client());

        // The first render is outside a request, and the CSRF token of the form needs a session.
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        self::getContainer()->get(RequestStack::class)->push($request);
    }

    public function testStartsANewMapWithoutLayers(): void
    {
        $component = $this->componentWithFakeEnter();

        $this->assertCount(0, $component->render()->crawler()->filter('h3'));

        $crawler = $component->call('addCollectionItem', ['name' => 'map[layers]'])->render()->crawler();

        $this->assertSame('Lag 1: Nyt lag', $crawler->filter('h3')->text());
        // The path fields have no choices before the layer has a data set.
        $this->assertStringContainsString('Vælg et datasæt til laget for at vælge egenskaberne.', $crawler->filter('#layer-0')->text());
        $this->assertSame('input', $crawler->filter('#map_layers_0_title_path')->nodeName());
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

    public function testLinksToTheEmbedCodeOfAStoredMap(): void
    {
        $map = $this->import('toilets');

        $crawler = $this->createLiveComponent(MapEditor::class, ['mapId' => (string) $map->getId()])->render()->crawler();

        $this->assertSame('/builder/'.$map->getId().'/embed', $crawler->filter('nav a')->attr('href'));
        $this->assertSame('Embedkode', $crawler->filter('nav a')->text());
        $this->assertCount(0, $crawler->filter('nav button[disabled]'));
        $this->assertCount(0, $crawler->filter('nav p'));
    }

    public function testShowsTheErrorsOfTheForm(): void
    {
        $component = $this->newMapComponent();
        $values = $this->newMap();
        $values['title'] = '';
        $values['viewZoom'] = '12';
        $values['layers'][0]['conditions'][0]['value'] = 'many';
        $values['layers'][0]['conditions'][0]['open'] = '1';

        // The test client throws the exception of an invalid form. In the
        // browser, the response is 422 with the errors next to the fields.
        try {
            $component->submitForm(['map' => $values], 'save');
            $this->fail('The form is not valid.');
        } catch (UnprocessableEntityHttpException $exception) {
            $this->assertStringContainsString('map.title: Udfyld feltet.', $exception->getMessage());
            $this->assertStringContainsString('map.viewZoom: Udfyld længdegrad, breddegrad og zoom, eller lad alle tre være tomme.', $exception->getMessage());
            $this->assertStringContainsString('map.layers.0.conditions.0.value: Skriv et tal, fx 2 eller 2.5.', $exception->getMessage());
            // A condition that the user can turn on and off needs a text.
            $this->assertStringContainsString('map.layers.0.conditions.0.label: Udfyld feltet.', $exception->getMessage());
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

    public function testAsksBeforeItCreatesANewMap(): void
    {
        // The same client as the request for the frame, so that they share the session.
        $client = self::getClient();
        $this->assertInstanceOf(KernelBrowser::class, $client);
        $component = $this->createLiveComponent(MapEditor::class, [], $client)
            ->call('addCollectionItem', ['name' => 'map[layers]'])
            ->call('addCollectionItem', ['name' => 'map[layers][0][conditions]']);

        $crawler = $component->submitForm(['map' => $this->newMap()], 'review')->render()->crawler();

        /** @var MapEditor $editor */
        $editor = $component->component();
        $this->assertNotNull($editor->confirmToken);
        $this->assertSame('Opret nyt kort?', $crawler->filter('dialog h2')->text());
        $this->assertSame(['Opret kortet', 'Tilbage til formularen'], $crawler->filter('dialog button')->each(static fn (Crawler $button): string => $button->text()));
        $this->assertNotNull($crawler->filter('dialog button')->eq(1)->attr('autofocus'));
        $this->assertSame('/builder/preview/'.$editor->confirmToken, $crawler->filter('dialog iframe')->attr('src'));
        // The question does not save the map.
        $this->assertCount(0, self::getContainer()->get(MapConfigurationRepository::class)->findAll());

        $crawler = $component->call('cancelSave')->render()->crawler();

        $this->assertCount(0, $crawler->filter('dialog'));
        $this->assertCount(0, self::getContainer()->get(MapConfigurationRepository::class)->findAll());

        // The frame of the question shows the map from the form.
        $client->request('GET', '/builder/preview/'.$editor->confirmToken);
        $this->assertSelectorTextSame('h1', 'Bænke i byen');
    }

    public function testWarnsBeforeItChangesAStoredMap(): void
    {
        $map = $this->import('toilets');
        $component = $this->createLiveComponent(MapEditor::class, ['mapId' => (string) $map->getId()]);

        $crawler = $component->submitForm(['map' => ['title' => 'Ny titel']], 'review')->render()->crawler();

        $this->assertSame('Gem ændringerne?', $crawler->filter('dialog h2')->text());
        $this->assertSame('Ændringerne vises med det samme på alle sider, som viser kortet.', $crawler->filter('dialog p')->text());
        $this->assertSame('Gem ændringerne', $crawler->filter('dialog button')->first()->text());

        $component->call('save');

        $this->assertSame('/builder/'.$map->getId().'/preview', $this->redirect($component));
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $this->assertSame('Ny titel', self::getContainer()->get(MapConfigurationRepository::class)->find((string) $map->getId())?->getConfig()->title);
    }

    public function testDoesNotAskIfTheSchemaHasErrors(): void
    {
        // A config that the form cannot correct: the URL of the report link must use https.
        $config = json_decode((string) file_get_contents($this->fixture('toilets')), false);
        $config->report->url = 'http://example.dk/form';
        $map = new MapConfiguration($config, [Renderer::Leaflet], []);
        self::getContainer()->get(MapConfigurationRepository::class)->save($map);
        $component = $this->createLiveComponent(MapEditor::class, ['mapId' => (string) $map->getId()]);

        $crawler = $component->call('review')->render()->crawler();

        $this->assertCount(0, $crawler->filter('dialog'));
        $this->assertSame('Kortet blev ikke gemt. Ret disse fejl.', $crawler->filter('[role="alert"] p')->text());
    }

    public function testPreviewsTheMapWithoutASave(): void
    {
        $map = $this->import('toilets');
        // The same client as the request for the frame, so that they share the session.
        $client = self::getClient();
        $this->assertInstanceOf(KernelBrowser::class, $client);
        $component = $this->createLiveComponent(MapEditor::class, ['mapId' => (string) $map->getId()], $client);

        $crawler = $component->submitForm(['map' => ['title' => 'Udkast']], 'preview')->render()->crawler();

        /** @var MapEditor $editor */
        $editor = $component->component();
        $this->assertNotNull($editor->previewToken);
        $this->assertSame('/builder/preview/'.$editor->previewToken, $crawler->filter('section iframe')->attr('src'));
        $this->assertSame('Forhåndsvisning af "Udkast"', $crawler->filter('section iframe')->attr('title'));

        // The frame shows the map from the form, and the stored map does not change.
        $client->request('GET', '/builder/preview/'.$editor->previewToken);
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextSame('h1', 'Udkast');
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $this->assertSame('Toiletter i Aarhus', self::getContainer()->get(MapConfigurationRepository::class)->find((string) $map->getId())?->getConfig()->title);
    }

    public function testUpdatesTheMapWhenTheViewStepOpens(): void
    {
        $map = $this->import('toilets');
        $component = $this->createLiveComponent(MapEditor::class, ['mapId' => (string) $map->getId()]);

        $crawler = $component->render()->crawler();

        // The frame loads only on the view step.
        $this->assertCount(0, $crawler->filter('iframe'));

        $crawler = $component->call('showStep', ['step' => MapEditor::STEP_VIEW])->render()->crawler();

        /** @var MapEditor $editor */
        $editor = $component->component();
        $this->assertNotNull($editor->previewToken);
        $this->assertSame('/builder/preview/'.$editor->previewToken, $crawler->filter('iframe')->attr('src'));
        $this->assertCount(1, $crawler->filter('button[data-action="map-view#use"]'));
    }

    public function testStaysOnTheViewStepIfTheMapHasAnError(): void
    {
        // A config that the form cannot correct: the URL of the report link must use https.
        $config = json_decode((string) file_get_contents($this->fixture('toilets')), false);
        $config->report->url = 'http://example.dk/form';
        $map = new MapConfiguration($config, [Renderer::Leaflet], []);
        self::getContainer()->get(MapConfigurationRepository::class)->save($map);
        $component = $this->createLiveComponent(MapEditor::class, ['mapId' => (string) $map->getId()]);

        $crawler = $component->call('showStep', ['step' => MapEditor::STEP_VIEW])->render()->crawler();

        $this->assertSame(MapEditor::STEP_VIEW, $component->component()->step);
        $this->assertStringContainsString('/report/url', $crawler->filter('[role="alert"] li')->text());
        $this->assertStringContainsString('(har fejl)', $crawler->filter('nav button')->first()->text());
        // The frame shows the stored map.
        $this->assertSame('/embed/leaflet/'.$map->getId(), $crawler->filter('iframe')->attr('src'));
    }

    public function testShowsNoMapForANewMapBeforeAPreview(): void
    {
        $component = $this->createLiveComponent(MapEditor::class);

        $crawler = $component->render()->crawler();

        $this->assertCount(0, $crawler->filter('iframe'));
        $this->assertCount(0, $crawler->filter('button[data-action="map-view#use"]'));
        $this->assertStringContainsString('Opdater kortet', $crawler->filter('#view-heading')->closest('section')?->text() ?? '');
    }

    public function testShowsTheErrorsOfTheSchemaInThePreview(): void
    {
        $config = json_decode((string) file_get_contents($this->fixture('toilets')), false);
        $config->report->url = 'http://example.dk/form';
        $map = new MapConfiguration($config, [Renderer::Leaflet], []);
        self::getContainer()->get(MapConfigurationRepository::class)->save($map);
        $component = $this->createLiveComponent(MapEditor::class, ['mapId' => (string) $map->getId()]);

        $crawler = $component->call('preview')->render()->crawler();

        $this->assertStringContainsString('/report/url', $crawler->filter('[role="alert"] li')->text());
        // The form shows the step of the error, and the menu marks it.
        $this->assertSame(MapEditor::STEP_SETTINGS, $component->component()->step);
        $this->assertStringContainsString('(har fejl)', $crawler->filter('nav [aria-current="step"]')->text());
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

    public function testShowsTheFieldsOfAConditionThatTheUserCanTurnOnAndOff(): void
    {
        $component = $this->newMapComponent();

        $crawler = $component->render()->crawler();

        $this->assertCount(1, $crawler->filter('#map_layers_0_conditions_0_open'));
        $this->assertCount(0, $crawler->filter('#map_layers_0_conditions_0_label'));

        $crawler = $component->set('map.layers.0.conditions.0.open', true)->render()->crawler();

        $this->assertCount(1, $crawler->filter('#map_layers_0_conditions_0_label'));
        $this->assertCount(1, $crawler->filter('#map_layers_0_conditions_0_checked'));
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

    public function testGivesTheChoicesOfThePathsOfTheDataSet(): void
    {
        $this->enter->features['merged-public-toilets'] = [FakeEnter::feature('urn:1', ['name' => 'Dokk1', 'address' => ['streetAddress' => 'Hack Kampmanns Plads 2']])];
        $map = $this->import('toilets');

        $crawler = $this->createLiveComponent(MapEditor::class, ['mapId' => (string) $map->getId()])->render()->crawler();

        foreach (['title_path', 'subtitle_path', 'detail_0_path', 'conditions_0_path'] as $field) {
            $this->assertSame('select', $crawler->filter('#map_layers_0_'.$field)->nodeName(), $field);
        }
        $options = $crawler->filter('#map_layers_0_subtitle_path option');
        $this->assertSame(['', 'address.streetAddress', 'name'], $options->each(static fn (Crawler $option): ?string => $option->attr('value')));
        $this->assertSame('address.streetAddress – fx Hack Kampmanns Plads 2', $options->eq(1)->text());
        $this->assertSame('address.streetAddress', $crawler->filter('#map_layers_0_subtitle_path option[selected]')->attr('value'));
        // A path that is not in the first entities stays as the first choice.
        $this->assertSame('description', $crawler->filter('#map_layers_0_detail_0_path option')->eq(1)->attr('value'));
        $this->assertSame('description', $crawler->filter('#map_layers_0_detail_0_path option[selected]')->attr('value'));
    }

    public function testTellsThatTheDataSetGaveNoPaths(): void
    {
        $map = $this->import('toilets');

        $crawler = $this->createLiveComponent(MapEditor::class, ['mapId' => (string) $map->getId()])->render()->crawler();

        $this->assertSame('input', $crawler->filter('#map_layers_0_title_path')->nodeName());
        $this->assertStringContainsString('Datasættet gav ingen egenskaber.', $crawler->filter('#layer-0')->text());
    }

    public function testShowsOneStepAtATime(): void
    {
        $map = $this->import('toilets');
        $component = $this->createLiveComponent(MapEditor::class, ['mapId' => (string) $map->getId()]);

        $crawler = $component->render()->crawler();

        $this->assertSame(['Indstillinger', 'Datasæt', 'Lag', 'Forhåndsvisning', 'Embedkode'], $crawler->filter('nav li')->each(static fn (Crawler $item): string => $item->text()));
        $this->assertSame('Indstillinger', $crawler->filter('nav [aria-current="step"]')->text());
        $this->assertNull($crawler->filter('#step-settings')->attr('hidden'));
        $this->assertNotNull($crawler->filter('#step-layers')->attr('hidden'));

        $crawler = $component->call('showStep', ['step' => MapEditor::STEP_LAYERS])->render()->crawler();

        $this->assertSame('Lag', $crawler->filter('nav [aria-current="step"]')->text());
        $this->assertNotNull($crawler->filter('#step-settings')->attr('hidden'));
        $this->assertNull($crawler->filter('#step-layers')->attr('hidden'));
        // The fields of a hidden step stay in the form.
        $this->assertCount(1, $crawler->filter('#map_title'));

        // A step that does not exist changes nothing.
        $component->call('showStep', ['step' => 'unknown']);
        $this->assertSame(MapEditor::STEP_LAYERS, $component->component()->step);
    }

    public function testAddsAndRemovesALayerWithEachDataSet(): void
    {
        $component = $this->componentWithFakeEnter();

        $crawler = $component->render()->crawler();

        $this->assertSame(['Bænke (benches) – ingen steder', 'Toiletter (merged-public-toilets) – ingen steder'], $crawler->filter('#step-datasets label')->each(static fn (Crawler $label): string => $label->text()));
        $this->assertCount(0, $crawler->filter('#step-datasets input:checked'));

        // Before a title and a data set, the layers, the view and the embed code are disabled.
        $this->assertSame(['Lag', 'Forhåndsvisning', 'Embedkode'], $crawler->filter('nav button[disabled]')->each(static fn (Crawler $button): string => $button->text()));
        $this->assertStringContainsString('Skriv en titel', $crawler->filter('nav p')->text());
        $component->call('showStep', ['step' => MapEditor::STEP_LAYERS]);
        $this->assertSame(MapEditor::STEP_SETTINGS, $component->component()->step);

        // A data set goes into a layer without a data set.
        $component->set('map.title', 'Bænke')->call('addCollectionItem', ['name' => 'map[layers]']);
        $crawler = $component->call('toggleDataSet', ['id' => 'benches'])->render()->crawler();

        $this->assertSame(['Lag 1: Bænke'], $crawler->filter('h3')->each(static fn (Crawler $heading): string => $heading->text()));
        $this->assertSame('benches', $crawler->filter('#map_layers_0_dataSet option[selected]')->attr('value'));
        $this->assertSame(['benches'], $crawler->filter('#step-datasets input:checked')->each(static fn (Crawler $input): ?string => $input->attr('value')));
        $this->assertSame(['Embedkode'], $crawler->filter('nav button[disabled]')->each(static fn (Crawler $button): string => $button->text()));
        $this->assertSame('Embedkoden kommer, når kortet er gemt.', $crawler->filter('nav p')->text());

        $crawler = $component->call('toggleDataSet', ['id' => 'merged-public-toilets'])->render()->crawler();

        $this->assertSame(['Lag 1: Bænke', 'Lag 2: Toiletter'], $crawler->filter('h3')->each(static fn (Crawler $heading): string => $heading->text()));
        // The data sets get the defaults of their type, from map_layer_defaults.yaml.
        $this->assertSame('#3661d8', $crawler->filter('#map_layers_0_colour')->attr('value'));
        $this->assertSame('#6b3fa0', $crawler->filter('#map_layers_1_colour')->attr('value'));
        $this->assertSame('toilet', $crawler->filter('#map_layers_1_icon option[selected]')->attr('value'));
        $this->assertSame('name', $crawler->filter('#map_layers_1_title_path')->attr('value'));
        $this->assertSame('Åbningstider', $crawler->filter('#map_layers_1_detail_1_label')->attr('value'));

        $crawler = $component->call('toggleDataSet', ['id' => 'benches'])->render()->crawler();

        $this->assertSame(['Lag 1: Toiletter'], $crawler->filter('h3')->each(static fn (Crawler $heading): string => $heading->text()));
        $this->assertSame(['merged-public-toilets'], $crawler->filter('#step-datasets input:checked')->each(static fn (Crawler $input): ?string => $input->attr('value')));

        // Without data sets, the layer step closes, and the form shows the data set step.
        $component->call('showStep', ['step' => MapEditor::STEP_LAYERS]);
        $component->call('toggleDataSet', ['id' => 'merged-public-toilets']);
        $this->assertSame(MapEditor::STEP_DATA_SETS, $component->component()->step);
    }

    public function testTellsThatTheListOfDataSetsIsNotAvailable(): void
    {
        $this->enter->down = true;

        $crawler = $this->createLiveComponent(MapEditor::class)->render()->crawler();

        $this->assertStringContainsString('Enter svarer ikke, så listen over datasæt mangler.', $crawler->filter('#step-datasets')->text());
    }

    public function testClosesTheLayersOfAStoredMapOnPageLoad(): void
    {
        $map = $this->import('toilets-and-parking');

        $crawler = $this->createLiveComponent(MapEditor::class, ['mapId' => (string) $map->getId()])->render()->crawler();

        $this->assertSame(['false', 'false'], $crawler->filter('h3 button')->each(static fn (Crawler $button): ?string => $button->attr('aria-expanded')));
        $this->assertNotNull($crawler->filter('#layer-0')->attr('hidden'));
        $this->assertSame('layer-0', $crawler->filter('h3 button')->first()->attr('aria-controls'));
        $this->assertStringContainsString('Datasæt: merged-public-toilets', $crawler->filter('#layer-0')->ancestors()->first()->text());
    }

    public function testOpensAndClosesALayer(): void
    {
        $map = $this->import('toilets-and-parking');
        $component = $this->createLiveComponent(MapEditor::class, ['mapId' => (string) $map->getId()]);

        $crawler = $component->call('toggleLayer', ['key' => '1'])->render()->crawler();
        $this->assertNull($crawler->filter('#layer-1')->attr('hidden'));
        $this->assertNotNull($crawler->filter('#layer-0')->attr('hidden'));

        $crawler = $component->call('toggleLayer', ['key' => '1'])->render()->crawler();
        $this->assertNotNull($crawler->filter('#layer-1')->attr('hidden'));
    }

    public function testOpensANewLayer(): void
    {
        $map = $this->import('toilets');
        $component = $this->createLiveComponent(MapEditor::class, ['mapId' => (string) $map->getId()]);

        $crawler = $component->call('addCollectionItem', ['name' => 'map[layers]'])->render()->crawler();

        $this->assertNotNull($crawler->filter('#layer-0')->attr('hidden'));
        $this->assertNull($crawler->filter('#layer-1')->attr('hidden'));
    }

    public function testOpensALayerWithAnErrorOfTheSchema(): void
    {
        // An SVG icon that the check rejects. The form keeps it, so the schema reports it.
        $config = json_decode((string) file_get_contents($this->fixture('toilets')), false);
        $config->layers[0]->icon = (object) ['svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><script>alert(1)</script></svg>'];
        $map = new MapConfiguration($config, [Renderer::Leaflet], []);
        self::getContainer()->get(MapConfigurationRepository::class)->save($map);
        $component = $this->createLiveComponent(MapEditor::class, ['mapId' => (string) $map->getId()]);

        $crawler = $component->call('save')->render()->crawler();

        $this->assertStringContainsString('/layers/0', $crawler->filter('[role="alert"]')->text());
        $this->assertNull($crawler->filter('#layer-0')->attr('hidden'));
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

        $crawler = $this->createLiveComponent(MapEditor::class)->call('addCollectionItem', ['name' => 'map[layers]'])->render()->crawler();

        $this->assertSame('text', $crawler->filter('#map_layers_0_dataSet')->attr('type'));
        $this->assertStringContainsString('Enter svarer ikke', $crawler->filter('#map_layers_0_dataSet_help')->text());
    }

    /**
     * A new map with one condition, as after a click on "Tilføj betingelse".
     */
    /**
     * A new map, with the client of the test without a reboot, so that the
     * actions also use the fake Enter.
     */
    private function componentWithFakeEnter(): \Symfony\UX\LiveComponent\Test\TestLiveComponent
    {
        $client = self::getClient();
        $this->assertInstanceOf(KernelBrowser::class, $client);
        $client->disableReboot();

        return $this->createLiveComponent(MapEditor::class, [], $client);
    }

    private function newMapComponent(): \Symfony\UX\LiveComponent\Test\TestLiveComponent
    {
        return $this->createLiveComponent(MapEditor::class)
            ->call('addCollectionItem', ['name' => 'map[layers]'])
            ->call('addCollectionItem', ['name' => 'map[layers][0][conditions]']);
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
