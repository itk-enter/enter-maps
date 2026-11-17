<?php

declare(strict_types=1);

namespace App\Tests\Integration\Builder;

use App\Builder\LayerDefaults;
use App\MapConfig\InvalidMapConfigException;
use App\MapConfig\MapConfigParser;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Validates each entry of config/packages/map_layer_defaults.yaml against the
 * JSON Schema, so that a mistake in the defaults fails here and not in the
 * map form.
 */
final class LayerDefaultsConfigTest extends KernelTestCase
{
    public function testMakesAValidLayerOfEachEntry(): void
    {
        $entries = self::getContainer()->get(LayerDefaults::class)->all();
        $this->assertArrayHasKey(LayerDefaults::DEFAULT_KEY, $entries);

        foreach ($entries as $type => $layer) {
            // The form adds these keys to the entry. The title path comes from the data set if the entry has none.
            $layer += ['id' => 'layer', 'dataSet' => 'data-set', 'label' => 'Lag', 'colour' => '#3661d8'];
            $layer['row']['title']['path'] ??= 'name';

            try {
                self::getContainer()->get(MapConfigParser::class)->parse(json_encode(['version' => 1, 'title' => 'Kort', 'layers' => [$layer]], \JSON_THROW_ON_ERROR));
            } catch (InvalidMapConfigException $exception) {
                $this->fail(\sprintf('The entry "%s" is not valid: %s', $type, implode(' ', $exception->errors)));
            }
        }
    }
}
