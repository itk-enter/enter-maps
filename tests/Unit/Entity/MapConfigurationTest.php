<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\MapConfiguration;
use App\Renderer\Renderer;
use PHPUnit\Framework\TestCase;

final class MapConfigurationTest extends TestCase
{
    public function testHoldsTheConfigurationAndTheSettingsOfEachRenderer(): void
    {
        $config = (object) ['version' => 1, 'title' => 'Toiletter'];
        $mapConfiguration = new MapConfiguration($config, [Renderer::Leaflet], ['leaflet' => ['title' => 'Toiletter']]);

        $this->assertSame($config, $mapConfiguration->getConfig());
        $this->assertSame([Renderer::Leaflet], $mapConfiguration->getRenderers());
        $this->assertEquals((object) ['title' => 'Toiletter'], $mapConfiguration->getMapJson(Renderer::Leaflet));
    }

    public function testGivesNoSettingsForARendererThatTheMapDoesNotUse(): void
    {
        $mapConfiguration = new MapConfiguration((object) ['version' => 1], [], []);

        $this->assertNull($mapConfiguration->getMapJson(Renderer::Leaflet));
    }

    public function testKeepsAnEmptyObjectInTheSettings(): void
    {
        $mapConfiguration = new MapConfiguration((object) [], [Renderer::Leaflet], ['leaflet' => ['values' => new \stdClass(), 'list' => []]]);

        $this->assertSame('{"values":{},"list":[]}', json_encode($mapConfiguration->getMapJson(Renderer::Leaflet)));
    }

    public function testGetsANewIdEachTime(): void
    {
        $first = new MapConfiguration((object) [], [], []);
        $second = new MapConfiguration((object) [], [], []);

        $this->assertNotEquals($first->getId(), $second->getId());
    }
}
