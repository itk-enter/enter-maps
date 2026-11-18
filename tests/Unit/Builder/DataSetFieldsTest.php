<?php

declare(strict_types=1);

namespace App\Tests\Unit\Builder;

use App\Builder\DataSetFields;
use App\Enter\EnterClient;
use App\Tests\Support\FakeEnter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class DataSetFieldsTest extends TestCase
{
    private FakeEnter $enter;

    private DataSetFields $fields;

    protected function setUp(): void
    {
        $this->enter = new FakeEnter();
        $this->enter->dataSets = [['id' => 'toilets', 'title' => 'Toilets']];
        $this->fields = new DataSetFields(new EnterClient($this->enter->client()), new ArrayAdapter());
    }

    public function testFindsThePathsWithAnExample(): void
    {
        $this->enter->features['toilets'] = [
            FakeEnter::feature('urn:1', [
                'name' => 'Dokk1',
                'address' => ['streetAddress' => 'Hack Kampmanns Plads 2', 'postalCode' => '8000'],
                'openingHours' => ['8-22', '10-16', 'Lukket', 'Mere'],
                'isAccessibleForFree' => true,
                'description' => '',
                'bad key' => 'x',
            ]),
            FakeEnter::feature('urn:2', [
                'name' => 'Havnen',
                'description' => str_repeat('a', 100),
                'totalSpotNumber' => 2,
                'closed' => null,
            ]),
        ];

        $this->assertSame([
            'address.postalCode' => '8000',
            'address.streetAddress' => 'Hack Kampmanns Plads 2',
            'closed' => '',
            // The first value that is not empty.
            'description' => str_repeat('a', 59).'…',
            'isAccessibleForFree' => 'true',
            'name' => 'Dokk1',
            'openingHours' => '8-22, 10-16, Lukket',
            'totalSpotNumber' => '2',
        ], $this->fields->paths('toilets'));
    }

    public function testReadsTheSampleOnce(): void
    {
        $this->enter->features['toilets'] = [FakeEnter::feature('urn:1', ['name' => 'Dokk1'])];

        $this->fields->paths('toilets');
        $this->fields->paths('toilets');

        $this->assertCount(2, $this->enter->requests, 'One request for /sources.json and one for the entities');
    }

    public function testGivesNoPathsWithoutADataSet(): void
    {
        $this->assertSame([], $this->fields->paths(null));
        $this->assertSame([], $this->fields->paths(''));
        $this->assertSame([], $this->fields->paths('benches'), 'Enter does not list it');
        $this->assertSame([], array_values(array_filter($this->enter->requests, static fn (string $url): bool => str_contains($url, '/entities'))), 'No request for entities');
    }

    public function testGivesNoPathsIfEnterDoesNotAnswer(): void
    {
        $this->enter->down = true;

        $this->assertSame([], $this->fields->paths('toilets'));

        // The failure is not in the cache, so the next call asks again.
        $this->enter->down = false;
        $this->enter->features['toilets'] = [FakeEnter::feature('urn:1', ['name' => 'Dokk1'])];
        $this->assertSame(['name' => 'Dokk1'], $this->fields->paths('toilets'));
    }
}
