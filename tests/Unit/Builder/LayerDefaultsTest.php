<?php

declare(strict_types=1);

namespace App\Tests\Unit\Builder;

use App\Builder\DataSetFields;
use App\Builder\LayerDefaults;
use App\Enter\EnterClient;
use App\Tests\Support\FakeEnter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class LayerDefaultsTest extends TestCase
{
    private FakeEnter $enter;

    private LayerDefaults $defaults;

    protected function setUp(): void
    {
        $this->enter = new FakeEnter();
        $this->enter->dataSets = [['id' => 'toilets', 'title' => 'Toiletter'], ['id' => 'benches', 'title' => 'Bænke']];
        $this->defaults = new LayerDefaults([
            LayerDefaults::DEFAULT_KEY => ['colour' => '#3661d8'],
            'PublicToilet' => ['colour' => '#6b3fa0', 'icon' => ['name' => 'toilet'], 'row' => ['title' => ['path' => 'name', 'fallback' => 'Toilet']], 'detail' => [['path' => 'description']]],
        ], new DataSetFields(new EnterClient($this->enter->client()), new ArrayAdapter()));
    }

    public function testGivesTheEntryOfTheType(): void
    {
        $layer = $this->defaults->forDataSet('toilets', 'PublicToilet');

        $this->assertEquals((object) [
            'colour' => '#6b3fa0',
            'icon' => (object) ['name' => 'toilet'],
            'row' => (object) ['title' => (object) ['path' => 'name', 'fallback' => 'Toilet']],
            'detail' => [(object) ['path' => 'description']],
        ], $layer);
        // The entry has a title, so the data set is not read.
        $this->assertSame([], $this->enter->requests);
    }

    public function testGivesTheDefaultEntryForAnotherTypeWithNameAsTitle(): void
    {
        $this->enter->features['benches'] = [FakeEnter::feature('urn:1', ['material' => 'Træ', 'name' => 'Bænk'])];

        foreach (['Bench', null] as $type) {
            $layer = $this->defaults->forDataSet('benches', $type);

            $this->assertEquals((object) ['colour' => '#3661d8', 'row' => (object) ['title' => (object) ['path' => 'name']]], $layer);
        }
    }

    public function testUsesTheFirstPropertyAsTitleWithoutName(): void
    {
        $this->enter->features['benches'] = [FakeEnter::feature('urn:1', ['seats' => 2, 'material' => 'Træ'])];

        $this->assertSame('material', $this->defaults->forDataSet('benches', null)->row->title->path);
    }

    public function testGivesNoTitleWithoutProperties(): void
    {
        $this->assertEquals((object) ['colour' => '#3661d8', 'row' => (object) ['title' => new \stdClass()]], $this->defaults->forDataSet('benches', null));
    }

    public function testGivesAllTheEntries(): void
    {
        $this->assertSame([LayerDefaults::DEFAULT_KEY, 'PublicToilet'], array_keys($this->defaults->all()));
    }
}
