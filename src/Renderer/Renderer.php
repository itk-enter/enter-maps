<?php

declare(strict_types=1);

namespace App\Renderer;

/**
 * A renderer that can show a map. Each renderer has a converter, and the
 * value is the key of its part in map_json.
 */
enum Renderer: string
{
    case Leaflet = 'leaflet';
}
