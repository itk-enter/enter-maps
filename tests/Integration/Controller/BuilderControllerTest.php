<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Builder\EmbedCode;
use App\Builder\MapConfigurationImporter;
use App\Entity\MapConfiguration;
use App\Repository\MapConfigurationRepository;
use App\Tests\Support\Database;
use App\Tests\Support\FakeEnter;
use App\Tests\Support\OidcTestLogin;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class BuilderControllerTest extends WebTestCase
{
    private const string UNKNOWN_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

    private KernelBrowser $browser;

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        Database::reset(self::getContainer()->get(EntityManagerInterface::class));
        OidcTestLogin::asEmployee($this->browser);
    }

    public function testTellsThatThereAreNoMaps(): void
    {
        $this->browser->request('GET', '/builder');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextSame('h1', 'Kort');
        $this->assertSelectorTextContains('main', 'Der er ingen kort endnu.');
        $this->assertSelectorNotExists('#deleted-heading');
    }

    public function testListsTheMapsWithTheirLinks(): void
    {
        $map = $this->import('toilets');

        $crawler = $this->browser->request('GET', '/builder');

        $this->assertResponseIsSuccessful();
        $row = $crawler->filter('tbody tr');
        $this->assertCount(1, $row);
        $this->assertStringContainsString('Toiletter i Aarhus', $row->text());
        $this->assertStringContainsString((string) $map->getId(), $row->text());
        $this->assertSame(
            ['/builder/'.$map->getId().'/edit', '/embed/leaflet/'.$map->getId(), '/builder/'.$map->getId().'/embed', '/builder/'.$map->getId().'/delete'],
            $row->filter('a')->each(static fn (Crawler $link): ?string => $link->attr('href')),
        );
    }

    public function testShowsTheFormOfANewMap(): void
    {
        $this->useFakeEnter();

        $this->browser->request('GET', '/builder/new');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextSame('h1', 'Nyt kort');
        $this->assertSelectorExists('form [name="map[title]"]');
        // A new map has no layers. The data set step adds them.
        $this->assertSelectorNotExists('h3');
    }

    public function testShowsTheFormOfAStoredMap(): void
    {
        $this->useFakeEnter();
        $map = $this->import('toilets');

        $this->browser->request('GET', '/builder/'.$map->getId().'/edit');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextSame('h1', 'Ret kortet "Toiletter i Aarhus"');
        $this->assertInputValueSame('map[title]', 'Toiletter i Aarhus');
    }

    public function testShowsThePreviewOfAMap(): void
    {
        $map = $this->import('toilets');

        $crawler = $this->browser->request('GET', '/builder/'.$map->getId().'/preview');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextSame('h1', 'Kortet "Toiletter i Aarhus"');
        $this->assertStringEndsWith('/embed/leaflet/'.$map->getId(), (string) $crawler->filter('main iframe')->attr('src'));
        $this->assertSame('/builder/'.$map->getId().'/edit', $crawler->selectLink('Ret kortet')->attr('href'));
    }

    public function testShowsTheEmbedCode(): void
    {
        $map = $this->import('toilets');

        $crawler = $this->browser->request('GET', '/builder/'.$map->getId().'/embed');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextSame('h1', 'Embedkode til "Toiletter i Aarhus"');
        $this->assertSame(
            self::getContainer()->get(EmbedCode::class)->html($map),
            $crawler->filter('[data-clipboard-target="source"]')->text(normalizeWhitespace: false),
        );
        // Without JavaScript, the copy button does nothing, so it is hidden.
        $this->assertNotNull($crawler->filter('[data-clipboard-target="button"]')->attr('hidden'));
    }

    public function testChangesTheHeightOfTheEmbedCode(): void
    {
        $map = $this->import('toilets');

        $crawler = $this->browser->request('GET', '/builder/'.$map->getId().'/embed');
        $crawler = $this->browser->submit($crawler->selectButton('Opdater koden')->form(['height' => '900']));

        $this->assertResponseIsSuccessful();
        $this->assertStringEndsWith('/builder/'.$map->getId().'/embed?height=900', (string) $this->browser->getRequest()->getUri());
        $this->assertSame('900', $crawler->filter('#height')->attr('value'));
        $this->assertStringContainsString('height="900"', $crawler->filter('[data-clipboard-target="source"]')->text());
    }

    public function testShowsTheDefaultHeightAndAnErrorForAHeightThatIsNotValid(): void
    {
        $map = $this->import('toilets');

        foreach (['50', '9000', 'high', ''] as $height) {
            $crawler = $this->browser->request('GET', '/builder/'.$map->getId().'/embed', ['height' => $height]);

            // Symfony gives 422 for a form with errors.
            $this->assertResponseStatusCodeSame(422);
            $this->assertCount(1, $crawler->filter('#height_error1'), $height);
            $this->assertStringContainsString('height="700"', $crawler->filter('[data-clipboard-target="source"]')->text(), $height);
        }
    }

    public function testDoesNotFindAnUnknownMap(): void
    {
        foreach (['embed', 'edit', 'preview'] as $page) {
            $this->browser->request('GET', '/builder/'.self::UNKNOWN_ID.'/'.$page);
            $this->assertResponseStatusCodeSame(404, $page);
        }

        // A preview that the session does not have.
        $this->browser->request('GET', '/builder/preview/'.str_repeat('0', 32));
        $this->assertResponseStatusCodeSame(404);

        $this->browser->request('GET', '/builder/'.self::UNKNOWN_ID.'/delete');
        $this->assertResponseStatusCodeSame(404);
    }

    public function testAsksBeforeItDeletesAMapAndThenRestoresIt(): void
    {
        $map = $this->import('toilets');

        $crawler = $this->browser->request('GET', '/builder/'.$map->getId().'/delete');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextSame('h1', 'Slet kortet "Toiletter i Aarhus"?');
        $this->assertNotNull(self::getContainer()->get(MapConfigurationRepository::class)->find((string) $map->getId()));

        $this->browser->submit($crawler->selectButton('Slet kortet')->form());

        $this->assertResponseRedirects('/builder');
        $crawler = $this->browser->followRedirect();
        $this->assertSelectorTextSame('[role="status"]', 'Kortet "Toiletter i Aarhus" er slettet.');
        $this->assertSelectorTextContains('main', 'Der er ingen kort endnu.');
        $this->assertSelectorTextContains('#deleted-heading + ul', 'Toiletter i Aarhus');
        $this->browser->request('GET', '/embed/leaflet/'.$map->getId());
        $this->assertResponseStatusCodeSame(404);

        $crawler = $this->browser->request('GET', '/builder');
        $this->browser->submit($crawler->filter('#deleted-heading + ul form')->form());

        $this->assertResponseRedirects('/builder');
        $this->browser->followRedirect();
        $this->assertSelectorTextSame('[role="status"]', 'Kortet "Toiletter i Aarhus" er gendannet.');
        $this->assertSelectorTextContains('tbody', 'Toiletter i Aarhus');
        $this->assertSelectorNotExists('#deleted-heading');
    }

    public function testDoesNotDeleteOrRestoreWithoutAValidToken(): void
    {
        $map = $this->import('toilets');

        $this->browser->request('POST', '/builder/'.$map->getId().'/delete', ['_token' => 'wrong']);

        $this->assertResponseRedirects('/builder');
        $this->browser->followRedirect();
        $this->assertSelectorTextSame('[role="alert"]', 'Siden var udløbet, så handlingen blev ikke udført. Prøv igen.');
        $this->assertSelectorTextContains('tbody', 'Toiletter i Aarhus');

        $this->browser->request('POST', '/builder/'.$map->getId().'/restore', ['_token' => 'wrong']);

        $this->assertResponseRedirects('/builder');
    }

    public function testDoesNotRestoreAMapThatIsNotDeleted(): void
    {
        $map = $this->import('toilets');
        $deleted = $this->import('toilets-and-parking');
        self::getContainer()->get(MapConfigurationRepository::class)->delete($deleted);
        // The restore form of the deleted map gives a valid token.
        $crawler = $this->browser->request('GET', '/builder');
        $token = $crawler->filter('#deleted-heading + ul input[name="_token"]')->attr('value');

        $this->browser->request('POST', '/builder/'.$map->getId().'/restore', ['_token' => $token]);

        $this->assertResponseStatusCodeSame(404);
    }

    private function useFakeEnter(): void
    {
        $enter = new FakeEnter();
        $enter->dataSets = [['id' => 'merged-public-toilets', 'title' => 'Toiletter']];
        self::getContainer()->set('enter.client', $enter->client());
    }

    private function import(string $fixture): MapConfiguration
    {
        return self::getContainer()->get(MapConfigurationImporter::class)->import((string) file_get_contents($this->fixture($fixture)));
    }

    private function fixture(string $name): string
    {
        return \dirname(__DIR__, 2).'/Fixtures/map-config/'.$name.'.json';
    }
}
