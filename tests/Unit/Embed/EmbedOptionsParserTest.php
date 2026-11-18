<?php

declare(strict_types=1);

namespace App\Tests\Unit\Embed;

use App\Embed\EmbedOptionsParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EmbedOptionsParserTest extends TestCase
{
    private EmbedOptionsParser $parser;

    protected function setUp(): void
    {
        $this->parser = new EmbedOptionsParser();
    }

    public function testUsesTheSettingsOfTheMapWithoutAQueryString(): void
    {
        $options = $this->parser->parse([], $this->settings());

        $this->assertSame('da', $options->locale);
        $this->assertSame('right', $options->sidebar);
        $this->assertFalse($options->list);
        $this->assertTrue($options->report);
        $this->assertSame(['zoom' => null, 'center' => null, 'layers' => null, 'select' => null], $options->forScript());
    }

    public function testAppliesTheValidOptions(): void
    {
        $options = $this->parser->parse([
            'zoom' => '16',
            'center' => '10.2, 56.15',
            'layers' => 'parking,toilets,parking',
            'select' => 'urn:ngsi-ld:PublicToilet:1',
            'sidebar' => 'off',
            'list' => '1',
            'report' => '0',
            'lang' => 'en',
        ], $this->settings());

        $this->assertSame('en', $options->locale);
        $this->assertSame('off', $options->sidebar);
        $this->assertTrue($options->list);
        $this->assertFalse($options->report);
        $this->assertSame([
            'zoom' => 16,
            'center' => [56.15, 10.2],
            'layers' => ['parking', 'toilets'],
            'select' => 'urn:ngsi-ld:PublicToilet:1',
        ], $options->forScript());
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidProvider(): iterable
    {
        yield 'zoom too large' => [['zoom' => '21']];
        yield 'zoom not a number' => [['zoom' => 'near']];
        yield 'center with one value' => [['center' => '10.2']];
        yield 'center out of range' => [['center' => '200,56']];
        yield 'center not numbers' => [['center' => 'a,b']];
        yield 'unknown layers only' => [['layers' => 'benches']];
        yield 'select too long' => [['select' => str_repeat('x', 501)]];
        yield 'unknown sidebar' => [['sidebar' => 'top']];
        yield 'list not a flag' => [['list' => 'yes']];
        yield 'unknown language' => [['lang' => 'de']];
        yield 'array value' => [['zoom' => ['16']]];
        yield 'empty value' => [['zoom' => '']];
    }

    /**
     * @param array<string, mixed> $query
     */
    #[DataProvider('invalidProvider')]
    public function testIgnoresAValueThatIsNotValid(array $query): void
    {
        $this->assertEquals($this->parser->parse([], $this->settings()), $this->parser->parse($query, $this->settings()));
    }

    public function testCannotAddAReportLinkThatTheMapDoesNotHave(): void
    {
        $settings = $this->settings();
        unset($settings->report);

        $this->assertFalse($this->parser->parse(['report' => '1'], $settings)->report);
    }

    public function testUsesTheDefaultsIfTheSettingsHaveNoSidebar(): void
    {
        $options = $this->parser->parse([], (object) ['locale' => 'en-GB']);

        $this->assertSame('en', $options->locale);
        $this->assertSame('left', $options->sidebar);
        $this->assertTrue($options->list);
        $this->assertNull($options->layers);
    }

    public function testGivesDanishWithoutAMap(): void
    {
        $this->assertSame('da', $this->parser->locale([]));
        $this->assertSame('en', $this->parser->locale(['lang' => 'en']));
    }

    private function settings(): \stdClass
    {
        /** @var \stdClass $settings */
        $settings = json_decode('{
            "locale": "da-DK",
            "sidebar": {"position": "right", "list": false},
            "layers": [{"id": "toilets"}, {"id": "parking"}, "not a layer"],
            "report": {"label": "Ret", "url": "https://example.com/{id}"}
        }', false, 512, \JSON_THROW_ON_ERROR);

        return $settings;
    }
}
