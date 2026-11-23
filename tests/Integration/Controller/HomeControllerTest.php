<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HomeControllerTest extends WebTestCase
{
    public function testShowsACardWithALinkToTheBuilder(): void
    {
        $browser = self::createClient();

        $crawler = $browser->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextSame('h1', 'Enter maps');
        $card = $crawler->filter('main article');
        $this->assertCount(1, $card);
        $this->assertSame('Kortbygger', $card->filter('h2')->text());
        $this->assertSame('/builder', $card->selectLink('Åbn kortbyggeren')->attr('href'));
    }
}
