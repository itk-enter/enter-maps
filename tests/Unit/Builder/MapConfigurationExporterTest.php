<?php

declare(strict_types=1);

namespace App\Tests\Unit\Builder;

use App\Builder\MapConfigurationExporter;
use App\Entity\MapConfiguration;
use App\Renderer\Renderer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\String\Slugger\AsciiSlugger;

final class MapConfigurationExporterTest extends TestCase
{
    private MapConfigurationExporter $exporter;

    protected function setUp(): void
    {
        $this->exporter = new MapConfigurationExporter(new AsciiSlugger());
    }

    public function testGivesTheConfigAsReadableJson(): void
    {
        $config = json_decode('{"version": 1, "title": "Toiletter i Århus", "url": "https://example.dk/a", "empty": {}, "list": []}', false);

        $json = $this->exporter->json(new MapConfiguration($config, [Renderer::Leaflet], []));

        $this->assertSame(<<<'JSON'
            {
                "version": 1,
                "title": "Toiletter i Århus",
                "url": "https://example.dk/a",
                "empty": {},
                "list": []
            }

            JSON, $json);
    }

    public function testNamesTheFileAfterTheTitleAndTheId(): void
    {
        $map = new MapConfiguration((object) ['version' => 1, 'title' => 'Toiletter i Århus'], [Renderer::Leaflet], []);

        $this->assertSame('toiletter-i-arhus-'.$map->getId().'.json', $this->exporter->filename($map));
    }

    public function testNamesTheFileAfterTheIdIfThereIsNoTitle(): void
    {
        $map = new MapConfiguration((object) ['version' => 1, 'title' => '!!!'], [Renderer::Leaflet], []);

        $this->assertSame($map->getId().'.json', $this->exporter->filename($map));
    }
}
