<?php

declare(strict_types=1);

namespace App\Tests\Integration\Builder;

use App\Builder\EmbedCode;
use App\Entity\MapConfiguration;
use App\Renderer\Renderer;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouterInterface;

final class EmbedCodeTest extends KernelTestCase
{
    public function testWritesAnIframeWithTheFrameOptions(): void
    {
        self::bootKernel();
        $map = new MapConfiguration((object) ['version' => 1, 'title' => 'Toiletter & "bænke"'], [Renderer::Leaflet], []);

        $code = self::getContainer()->get(EmbedCode::class)->html($map);
        // Outside a request, the address of the site comes from DEFAULT_URI.
        $site = self::getContainer()->get(RouterInterface::class)->getContext()->getHost();

        $this->assertSame(<<<HTML
            <iframe src="http://{$site}/embed/leaflet/{$map->getId()}"
                    title="Toiletter &amp; &quot;bænke&quot;"
                    width="100%" height="700" loading="lazy" style="border:0"
                    referrerpolicy="strict-origin-when-cross-origin"></iframe>
            HTML, $code);
    }

    public function testWritesADivWithALinkAndTheScript(): void
    {
        self::bootKernel();
        $map = new MapConfiguration((object) ['version' => 1, 'title' => 'Toiletter & "bænke"'], [Renderer::Leaflet], []);

        $code = self::getContainer()->get(EmbedCode::class)->script($map);
        $site = self::getContainer()->get(RouterInterface::class)->getContext()->getHost();

        $this->assertSame(<<<HTML
            <div data-enter-map="{$map->getId()}"><a href="http://{$site}/embed/leaflet/{$map->getId()}">Toiletter &amp; &quot;bænke&quot;</a></div>
            <script src="http://{$site}/embed/v1.js" async></script>
            HTML, $code);
    }

    public function testWritesAnEmptyTitleIfTheConfigHasNoTitle(): void
    {
        self::bootKernel();
        $map = new MapConfiguration((object) ['version' => 1], [Renderer::Leaflet], []);

        $this->assertStringContainsString('title=""', self::getContainer()->get(EmbedCode::class)->html($map));
        $this->assertStringContainsString('"></a></div>', self::getContainer()->get(EmbedCode::class)->script($map));
    }
}
