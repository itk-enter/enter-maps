<?php

declare(strict_types=1);

namespace App\Tests\Integration\Builder;

use App\Builder\DataSetChoices;
use App\Enter\EnterClient;
use App\Tests\Support\FakeEnter;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Contracts\Translation\TranslatorInterface;

final class DataSetChoicesTest extends KernelTestCase
{
    private FakeEnter $enter;

    private DataSetChoices $choices;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->enter = new FakeEnter();
        $this->enter->dataSets = [
            ['id' => 'toilets', 'title' => 'Toiletter'],
            ['id' => 'benches', 'title' => 'Bænke'],
            ['id' => 'one', 'title' => 'Én'],
            ['id' => 'broken', 'title' => 'Fejl'],
        ];
        $this->enter->features['toilets'] = [FakeEnter::feature('urn:1'), FakeEnter::feature('urn:2')];
        $this->enter->features['one'] = [FakeEnter::feature('urn:3')];
        $this->enter->failingDataSets = ['broken'];
        $this->choices = new DataSetChoices(new EnterClient($this->enter->client()), new ArrayAdapter(), self::getContainer()->get(TranslatorInterface::class));
    }

    public function testGivesTheDataSetsWithTheNumberOfPlaces(): void
    {
        $this->assertSame([
            'Bænke (benches) – ingen steder' => 'benches',
            'Én (one) – 1 sted' => 'one',
            'Fejl (broken)' => 'broken',
            'Toiletter (toilets) – 2 steder' => 'toilets',
        ], $this->choices->choices());
    }

    public function testGivesTheTitleAndTheLabelOfEachDataSet(): void
    {
        $this->assertSame([
            'benches' => ['title' => 'Bænke', 'label' => 'Bænke (benches) – ingen steder'],
            'one' => ['title' => 'Én', 'label' => 'Én (one) – 1 sted'],
            'broken' => ['title' => 'Fejl', 'label' => 'Fejl (broken)'],
            'toilets' => ['title' => 'Toiletter', 'label' => 'Toiletter (toilets) – 2 steder'],
        ], $this->choices->dataSets());
    }

    public function testCountsOnceForTheCache(): void
    {
        $this->choices->choices();
        $requests = \count($this->enter->requests);

        $this->choices->choices();

        // Only /sources.json again.
        $this->assertCount($requests + 1, $this->enter->requests);
    }

    public function testGivesNullIfEnterDoesNotAnswer(): void
    {
        $this->enter->down = true;

        $this->assertNull($this->choices->choices());
    }
}
