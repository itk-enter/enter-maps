<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enter;

use App\Enter\DataProxy;
use App\Enter\EnterClient;
use App\Enter\EnterUnavailableException;
use App\Enter\InvalidWhereException;
use App\Enter\UnknownDataSetException;
use App\Tests\Support\FakeEnter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DataProxyTest extends TestCase
{
    private FakeEnter $enter;

    private DataProxy $proxy;

    protected function setUp(): void
    {
        $this->enter = new FakeEnter();
        $this->enter->dataSets = [['id' => 'toilets', 'title' => 'Toilets']];
        $this->enter->features['toilets'] = [FakeEnter::feature('urn:1')];

        $this->proxy = new DataProxy(new EnterClient($this->enter->client()));
    }

    public function testGetFeaturesReturnsTheFeaturesOfTheDataSet(): void
    {
        $features = $this->proxy->getFeatures('toilets', null);

        $this->assertCount(1, $features);
        $this->assertSame('urn:1', $features[0]['id']);
    }

    public function testGetFeaturesAsksEnterEachTime(): void
    {
        $this->proxy->getFeatures('toilets', null);
        $this->proxy->getFeatures('toilets', null);

        // Each call: one request for /sources.json, one for the entities.
        $this->assertCount(4, $this->enter->requests);
    }

    public function testGetFeaturesFailsWhenEnterIsDown(): void
    {
        $this->enter->down = true;

        $this->expectException(EnterUnavailableException::class);

        $this->proxy->getFeatures('toilets', null);
    }

    public function testGetFeaturesFailsForAnUnknownDataSet(): void
    {
        $this->expectException(UnknownDataSetException::class);

        $this->proxy->getFeatures('benches', null);
    }

    /**
     * @return iterable<string, array{string|null, string|null}>
     */
    public static function emptyWhereProvider(): iterable
    {
        yield 'null' => [null, null];
        yield 'empty' => ['', null];
        yield 'spaces' => ['  ', null];
        yield 'trimmed' => [' a==1 ', 'a==1'];
    }

    #[DataProvider('emptyWhereProvider')]
    public function testGetFeaturesTrimsTheWhereFilter(?string $where, ?string $expected): void
    {
        $this->proxy->getFeatures('toilets', $where);

        parse_str((string) parse_url($this->enter->requests[1], \PHP_URL_QUERY), $query);
        $this->assertSame('sourceId=="toilets"'.(null === $expected ? '' : ';'.$expected), $query['q']);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidWhereProvider(): iterable
    {
        yield 'too long' => [str_repeat('a', DataProxy::MAX_WHERE_LENGTH + 1), 'longer than'];
        yield 'new line' => ["a==1\nb==2", 'not permitted'];
        yield 'ampersand' => ['a==1&b==2', 'not permitted'];
    }

    #[DataProvider('invalidWhereProvider')]
    public function testGetFeaturesRejectsAnInvalidWhereFilter(string $where, string $message): void
    {
        $this->expectException(InvalidWhereException::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->proxy->getFeatures('toilets', $where);
    }

    public function testGetFeaturesAcceptsDanishLettersInTheWhereFilter(): void
    {
        $features = $this->proxy->getFeatures('toilets', 'name=="Ådalen"');

        $this->assertCount(1, $features);
    }
}
