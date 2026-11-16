<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Builder\MapConfigurationImporter;
use App\Entity\MapConfiguration;
use App\Repository\MapConfigurationRepository;
use App\Tests\Support\Database;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Field\FileFormField;
use Symfony\Component\DomCrawler\Form;

final class BuilderControllerTest extends WebTestCase
{
    private const string UNKNOWN_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

    private KernelBrowser $browser;

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        Database::reset(self::getContainer()->get(EntityManagerInterface::class));
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
            ['/embed/leaflet/'.$map->getId(), '/builder/'.$map->getId().'/export', '/builder/'.$map->getId().'/delete'],
            $row->filter('a')->each(static fn (Crawler $link): ?string => $link->attr('href')),
        );
    }

    public function testImportsAMap(): void
    {
        $crawler = $this->browser->request('GET', '/builder');
        $form = $crawler->selectButton('Importér')->form();
        $this->upload($form, $this->fixture('toilets'));

        $this->browser->submit($form);

        $this->assertResponseRedirects('/builder');
        $this->browser->followRedirect();
        $this->assertSelectorTextSame('[role="status"]', 'Kortet "Toiletter i Aarhus" er importeret.');
        $this->assertSelectorTextContains('tbody', 'Toiletter i Aarhus');
    }

    public function testTellsWhyAFileIsNotAValidMap(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'map').'.json';
        file_put_contents($path, '{"version": 1}');
        $crawler = $this->browser->request('GET', '/builder');
        $form = $crawler->selectButton('Importér')->form();
        $this->upload($form, $path);

        $crawler = $this->browser->submit($form);

        $this->assertResponseStatusCodeSame(422);
        $errors = $crawler->filter('#import_map_configuration_file_error1, #import_map_configuration_file_error2')->each(
            static fn (Crawler $error): string => $error->text(),
        );
        $this->assertSame('Filen er ikke en gyldig kortkonfiguration.', $errors[0]);
        $this->assertStringContainsString('title', $errors[1]);
        $this->assertSame('true', $crawler->filter('#import_map_configuration_file')->attr('aria-invalid'));
        $this->assertSame([], self::getContainer()->get(MapConfigurationRepository::class)->findAll());
        unlink($path);
    }

    public function testRejectsAFileThatIsNotJson(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'map').'.txt';
        file_put_contents($path, 'Not JSON');
        $crawler = $this->browser->request('GET', '/builder');
        $form = $crawler->selectButton('Importér')->form();
        $this->upload($form, $path);

        $this->browser->submit($form);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextSame('#import_map_configuration_file_error1', 'Filen skal være en JSON-fil (.json).');
        unlink($path);
    }

    public function testExportsTheConfigOfAMap(): void
    {
        $map = $this->import('toilets');

        $this->browser->request('GET', '/builder/'.$map->getId().'/export');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
        $this->assertResponseHeaderSame('Content-Disposition', 'attachment; filename=toiletter-i-aarhus-'.$map->getId().'.json');
        $this->assertEquals(
            json_decode((string) file_get_contents($this->fixture('toilets')), false),
            json_decode((string) $this->browser->getResponse()->getContent(), false),
        );
    }

    public function testDoesNotFindAnUnknownMap(): void
    {
        $this->browser->request('GET', '/builder/'.self::UNKNOWN_ID.'/export');
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

    private function upload(Form $form, string $path): void
    {
        $field = $form['import_map_configuration[file]'];
        $this->assertInstanceOf(FileFormField::class, $field);
        $field->upload($path);
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
