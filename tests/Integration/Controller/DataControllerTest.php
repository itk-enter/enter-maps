<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Tests\Support\FakeEnter;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DataControllerTest extends WebTestCase
{
    private KernelBrowser $browser;

    private FakeEnter $enter;

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        // Keep the fake Enter between the requests of a test.
        $this->browser->disableReboot();
        $this->enter = new FakeEnter();
        $this->enter->dataSets = [['id' => 'toilets', 'title' => 'Toilets']];
        $this->enter->features['toilets'] = [FakeEnter::feature('urn:1', ['name' => 'Ådalen'])];
        self::getContainer()->set('enter.client', $this->enter->client());
    }

    public function testReturnsTheFeaturesAsGeoJson(): void
    {
        $this->browser->request('GET', '/data/toilets.geojson');

        $response = $this->browser->getResponse();
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/geo+json');
        $this->assertSame([
            'type' => 'FeatureCollection',
            'features' => [[
                'type' => 'Feature',
                'id' => 'urn:1',
                'geometry' => ['type' => 'Point', 'coordinates' => [10.2, 56.15]],
                'properties' => ['name' => 'Ådalen'],
            ]],
        ], json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR));
        $this->assertStringContainsString('Ådalen', (string) $response->getContent());
        $this->assertResponseNotHasHeader('Set-Cookie');
    }

    public function testSetsCacheHeaders(): void
    {
        $this->browser->request('GET', '/data/toilets.geojson');

        $this->assertResponseHeaderSame('Cache-Control', 'max-age=60, public');
    }

    public function testPassesTheWhereFilterToEnter(): void
    {
        $this->browser->request('GET', '/data/toilets.geojson', ['where' => 'isAccessibleForFree==true']);

        $this->assertResponseIsSuccessful();
        parse_str((string) parse_url($this->enter->requests[1], \PHP_URL_QUERY), $query);
        $this->assertSame('sourceId=="toilets";isAccessibleForFree==true', $query['q']);
    }

    public function testReturnsNotFoundForAnUnknownDataSet(): void
    {
        $this->browser->request('GET', '/data/benches.geojson');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testReturnsBadRequestForAnInvalidWhereFilter(): void
    {
        $this->browser->request('GET', '/data/toilets.geojson', ['where' => 'a==1&b==2']);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testReturnsServiceUnavailableWhenEnterIsDown(): void
    {
        $this->enter->down = true;

        $this->browser->request('GET', '/data/toilets.geojson');

        $this->assertResponseStatusCodeSame(503);
        $this->assertResponseHeaderSame('Retry-After', '60');
    }
}
