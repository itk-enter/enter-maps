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
        $this->assertCount(1, $crawler->filter('#filter-0'));
        $this->assertStringContainsString('Ret eller tilføj oplysninger', (string) $crawler->filter('#map-detail template')->html());
    }

    public function testShowsOnlyTheMapWithoutTheSidebar(): void
    {
        $id = $this->store('toilets');

        $crawler = $this->browser->request('GET', '/embed/leaflet/'.$id.'?sidebar=off&report=0');

        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $crawler->filter('#search'));
        $this->assertCount(0, $crawler->filter('#map-list'));
        $this->assertCount(1, $crawler->filter('#map-detail'));
        $this->assertStringNotContainsString('Ret eller tilføj oplysninger', (string) $crawler->filter('#map-detail template')->html());
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
