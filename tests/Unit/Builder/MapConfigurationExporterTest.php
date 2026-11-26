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
    public function testWritesTheConfigAsFormattedJson(): void
    {
        $map = new MapConfiguration((object) ['version' => 1, 'title' => 'Bænke i byen', 'filters' => (object) [], 'url' => 'https://example.dk/a'], [Renderer::Leaflet], []);

        $json = new MapConfigurationExporter(new AsciiSlugger())->json($map);

        // "{}" stays an object, and the slashes and the Danish letters stay as they are.
        $this->assertSame(<<<'JSON'
            {
                "version": 1,
                "title": "Bænke i byen",
                "filters": {},
                "url": "https://example.dk/a"
            }

            JSON, $json);
    }

    public function testNamesTheFileAfterTheTitleOrElseTheId(): void
    {
        $exporter = new MapConfigurationExporter(new AsciiSlugger());
        $map = new MapConfiguration((object) ['version' => 1, 'title' => 'Bænke i byen'], [Renderer::Leaflet], []);
        $untitled = new MapConfiguration((object) ['version' => 1, 'title' => '!!'], [Renderer::Leaflet], []);

        $this->assertSame('baenke-i-byen.json', $exporter->fileName($map));
        $this->assertSame($untitled->getId().'.json', $exporter->fileName($untitled));
    }
}
