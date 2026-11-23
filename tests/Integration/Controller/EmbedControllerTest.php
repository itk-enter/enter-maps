<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Entity\MapConfiguration;
use App\MapConfig\MapConfigurationFactory;
use App\Renderer\Renderer;
use App\Tests\Support\Database;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class EmbedControllerTest extends WebTestCase
{
    private KernelBrowser $browser;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        Database::reset($this->entityManager);
    }

    public function testShowsTheMap(): void
    {
        $id = $this->store('toilets-and-parking');

        $crawler = $this->browser->request('GET', '/embed/leaflet/'.$id);

        $this->assertResponseIsSuccessful();
        $this->assertResponseNotHasHeader('Set-Cookie');
        $this->assertSame('da', $crawler->filter('html')->attr('lang'));
        $this->assertSelectorTextSame('h1', 'Tilgængelighed i midtbyen');
        $this->assertSame('map', $crawler->filter('main')->attr('data-controller'));
        $this->assertSame(['toilets', 'parking'], $crawler->filter('template[data-map-target="marker"]')->each(
            static fn (Crawler $marker): ?string => $marker->attr('data-layer-id'),
        ));
        $this->assertCount(2, $crawler->filter('[data-map-filters-target="layer"]:checked'));

        $settings = json_decode((string) $crawler->filter('main')->attr('data-map-settings-value'), false, 512, \JSON_THROW_ON_ERROR);
        $this->assertEquals($this->settings($id), $settings);
    }

    public function testAppliesTheEmbedOptions(): void
    {
        $id = $this->store('toilets-and-parking');

        $crawler = $this->browser->request('GET', '/embed/leaflet/'.$id.'?layers=parking&zoom=17&lang=en&list=0');

        $this->assertResponseIsSuccessful();
        $this->assertSame('en', $crawler->filter('html')->attr('lang'));
        $this->assertSame(['parking'], $crawler->filter('[data-map-filters-target="layer"]:checked')->each(
            static fn (Crawler $input): ?string => $input->attr('value'),
        ));
        $this->assertCount(0, $crawler->filter('#map-list'));
        $this->assertSame(
            ['zoom' => 17, 'center' => null, 'layers' => ['parking'], 'select' => null],
            json_decode((string) $crawler->filter('main')->attr('data-map-options-value'), true, 512, \JSON_THROW_ON_ERROR),
        );
    }

    public function testShowsTheSearchFieldAndTheReportLinkOfTheConfiguration(): void
    {
        $id = $this->store('toilets');

        $crawler = $this->browser->request('GET', '/embed/leaflet/'.$id);

        $this->assertResponseIsSuccessful();
        $this->assertSame('combobox', $crawler->filter('#search')->attr('role'));
        // The search controller gets the token and the municipality of Adressevælger from .env.
        $search = $crawler->filter('[data-controller="address-search"]');
        $this->assertSame('adressevaelger123', $search->attr('data-address-search-token-value'));
        $this->assertSame('0751', $search->attr('data-address-search-municipality-value'));
        $this->assertCount(1, $crawler->filter('#filter-0'));
        $this->assertStringContainsString('Ret eller tilføj oplysninger', $crawler->filter('#map-detail template')->html());
    }

    public function testShowsTheSidebarWithSidebarOff(): void
    {
        $id = $this->store('toilets');

        $crawler = $this->browser->request('GET', '/embed/leaflet/'.$id.'?sidebar=off&report=0');

        // "off" is not a valid value, so the page shows the sidebar of the configuration.
        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('#search'));
        $this->assertCount(1, $crawler->filter('#map-list'));
        $this->assertCount(1, $crawler->filter('#map-detail'));
        $this->assertStringNotContainsString('Ret eller tilføj oplysninger', $crawler->filter('#map-detail template')->html());
    }

    public function testShowsTheRouteLinkOnlyIfTheConfigurationHasIt(): void
    {
        $shown = $this->browser->request('GET', '/embed/leaflet/'.$this->store('toilets'));
        $this->assertCount(1, $shown->filter('#map-detail template')->filter('[data-field="route"]'));

        $config = json_decode((string) file_get_contents(\dirname(__DIR__, 2).'/Fixtures/map-config/toilets.json'), true, 512, \JSON_THROW_ON_ERROR);
        $config['sidebar']['routeLink'] = false;
        $mapConfiguration = self::getContainer()->get(MapConfigurationFactory::class)->create(json_encode($config, \JSON_THROW_ON_ERROR), [Renderer::Leaflet]);
        $this->entityManager->persist($mapConfiguration);
        $this->entityManager->flush();

        $hidden = $this->browser->request('GET', '/embed/leaflet/'.$mapConfiguration->getId());
        $this->assertCount(0, $hidden->filter('#map-detail template')->filter('[data-field="route"]'));
    }

    public function testShowsTheRouteLinkOfAMapThatWasSavedWithoutTheSetting(): void
    {
        $id = $this->store('toilets');
        $mapConfiguration = $this->entityManager->find(MapConfiguration::class, $id);
        $this->assertInstanceOf(MapConfiguration::class, $mapConfiguration);
        $settings = json_decode((string) json_encode($mapConfiguration->getMapJson(Renderer::Leaflet)), true, 512, \JSON_THROW_ON_ERROR);
        unset($settings['sidebar']['routeLink']);
        $mapConfiguration->change($mapConfiguration->getConfig(), [Renderer::Leaflet], [Renderer::Leaflet->value => $settings]);
        $this->entityManager->flush();

        $crawler = $this->browser->request('GET', '/embed/leaflet/'.$id);

        $this->assertCount(1, $crawler->filter('#map-detail template')->filter('[data-field="route"]'));
    }

    public function testShowsTheSpinnerOnlyIfTheConfigurationHasIt(): void
    {
        $withSpinner = $this->browser->request('GET', '/embed/leaflet/'.$this->store('toilets-and-parking'));
        $this->assertCount(1, $withSpinner->filter('[data-map-target="spinner"]'));

        $withoutSpinner = $this->browser->request('GET', '/embed/leaflet/'.$this->store('toilets'));
        $this->assertCount(0, $withoutSpinner->filter('[data-map-target="spinner"]'));
    }

    public function testShowsTheConditionsOfALayerBelowTheLayer(): void
    {
        $crawler = $this->browser->request('GET', '/embed/leaflet/'.$this->store('toilets-and-parking'));

        $group = $crawler->filter('[role="group"][aria-label="Filtre for Offentlige toiletter"]');
        $this->assertSame('toilets:0', $group->filter('input')->attr('value'));
        $this->assertNotNull($group->filter('input')->attr('checked'));
        $this->assertSame('Kørestolsvenligt', trim($group->filter('label')->text()));
        $this->assertNull($crawler->filter('[value="parking:0"]')->attr('checked'));
    }

    public function testShowsTheNameOfTheLayerAboveItsConditionsWithoutTheLayerCheckboxes(): void
    {
        $config = json_decode((string) file_get_contents(\dirname(__DIR__, 2).'/Fixtures/map-config/toilets-and-parking.json'), true, 512, \JSON_THROW_ON_ERROR);
        $config['sidebar']['categories'] = false;
        $mapConfiguration = self::getContainer()->get(MapConfigurationFactory::class)->create(json_encode($config, \JSON_THROW_ON_ERROR), [Renderer::Leaflet]);
        $this->entityManager->persist($mapConfiguration);
        $this->entityManager->flush();

        $crawler = $this->browser->request('GET', '/embed/leaflet/'.$mapConfiguration->getId());

        $this->assertCount(0, $crawler->filter('[data-map-filters-target="layer"]'));
        $this->assertSame(['Offentlige toiletter', 'Handicapparkering'], $crawler->filter('[data-controller="map-filters"] > fieldset > legend')->each(
            static fn (Crawler $legend): string => $legend->text(),
        ));
        $this->assertCount(2, $crawler->filter('[data-map-filters-target="openFilter"]'));
    }

    public function testSendsTheHeightToThePageWithAutoheight(): void
    {
        $id = $this->store('toilets');

        $crawler = $this->browser->request('GET', '/embed/leaflet/'.$id.'?autoheight=1');

        $this->assertResponseIsSuccessful();
        $this->assertSame('map embed-height', $crawler->filter('main')->attr('data-controller'));
        // 50vh would depend on the height of the frame, which depends on the content.
        $this->assertStringContainsString('h-96', (string) $crawler->filter('[data-map-target="map"]')->attr('class'));

        $crawler = $this->browser->request('GET', '/embed/leaflet/'.$id);
        $this->assertStringContainsString('h-[50vh]', (string) $crawler->filter('[data-map-target="map"]')->attr('class'));
    }

    public function testGivesTheEmbedScriptWithTheAddressOfTheSite(): void
    {
        $this->browser->request('GET', 'https://maps.example.dk/embed/v1.js');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/javascript; charset=UTF-8');
        $this->assertResponseNotHasHeader('Set-Cookie');
        $this->assertTrue($this->browser->getResponse()->headers->hasCacheControlDirective('public'));
        $this->assertSame('300', $this->browser->getResponse()->headers->getCacheControlDirective('max-age'));
        $script = (string) $this->browser->getResponse()->getContent();
        $this->assertStringContainsString('const ORIGIN = "https:\/\/maps.example.dk";', $script);
        $this->assertStringContainsString('const HEIGHT = 700;', $script);
        $this->assertStringNotContainsString('{#', $script);
    }

    public function testSaysThatAMapThatDoesNotExistIsNotAvailable(): void
    {
        $crawler = $this->browser->request('GET', '/embed/leaflet/01M7A9NR64B762Z5NDXFSSAM1Z?lang=en');

        $this->assertResponseStatusCodeSame(404);
        $this->assertSelectorTextSame('h1', 'The map is not available');
        $this->assertSame('en', $crawler->filter('html')->attr('lang'));
    }

    public function testSaysThatADeletedMapIsNotAvailable(): void
    {
        $id = $this->store('toilets');
        $mapConfiguration = $this->entityManager->find(MapConfiguration::class, $id);
        $this->assertInstanceOf(MapConfiguration::class, $mapConfiguration);
        $this->entityManager->remove($mapConfiguration);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $this->browser->request('GET', '/embed/leaflet/'.$id);

        $this->assertResponseStatusCodeSame(404);
        $this->assertSelectorTextSame('h1', 'Kortet er ikke tilgængeligt');
    }

    public function testRejectsAnIdThatIsNotAUlid(): void
    {
        $this->browser->request('GET', '/embed/leaflet/not-an-id');

        $this->assertResponseStatusCodeSame(404);
    }

    private function store(string $fixture): string
    {
        $json = (string) file_get_contents(\dirname(__DIR__, 2).'/Fixtures/map-config/'.$fixture.'.json');
        $mapConfiguration = self::getContainer()->get(MapConfigurationFactory::class)->create($json, [Renderer::Leaflet]);
        $this->entityManager->persist($mapConfiguration);
        $this->entityManager->flush();

        return (string) $mapConfiguration->getId();
    }

    private function settings(string $id): ?\stdClass
    {
        return $this->entityManager->find(MapConfiguration::class, $id)?->getMapJson(Renderer::Leaflet);
    }
}
