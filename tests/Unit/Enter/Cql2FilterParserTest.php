<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enter;

use App\Enter\Cql2FilterParser;
use App\Enter\InvalidFilterException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class Cql2FilterParserTest extends TestCase
{
    private Cql2FilterParser $parser;

    protected function setUp(): void
    {
        $this->parser = new Cql2FilterParser(\dirname(__DIR__, 3).'/config/schema/cql2-filter.json');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function filterProvider(): iterable
    {
        yield 'equal to true' => ['{"op": "=", "args": [{"property": "isAccessibleForFree"}, true]}', 'isAccessibleForFree==true'];
        yield 'equal to false' => ['{"op": "=", "args": [{"property": "isAccessibleForFree"}, false]}', 'isAccessibleForFree==false'];
        yield 'not equal to a text' => ['{"op": "<>", "args": [{"property": "fee"}, "yes"]}', 'fee!="yes"'];
        yield 'less than' => ['{"op": "<", "args": [{"property": "totalSpotNumber"}, 5]}', 'totalSpotNumber<5'];
        yield 'less than or equal' => ['{"op": "<=", "args": [{"property": "totalSpotNumber"}, 5]}', 'totalSpotNumber<=5'];
        yield 'greater than a decimal' => ['{"op": ">", "args": [{"property": "height"}, 1.5]}', 'height>1.5'];
        yield 'greater than or equal' => ['{"op": ">=", "args": [{"property": "height"}, 2]}', 'height>=2'];
        yield 'Danish letters' => ['{"op": "=", "args": [{"property": "name"}, "Ådalen"]}', 'name=="Ådalen"'];
        yield 'operator characters in a text' => ['{"op": "=", "args": [{"property": "name"}, "a;b|c(d)"]}', 'name=="a;b|c(d)"'];
        yield 'key in a value' => ['{"op": "=", "args": [{"property": "address.addressLocality"}, "Aarhus"]}', 'address[addressLocality]=="Aarhus"'];
        yield 'in' => ['{"op": "in", "args": [{"property": "wheelchairAccessible"}, ["yes", "limited"]]}', 'wheelchairAccessible=="yes","limited"'];
        yield 'is null' => ['{"op": "isNull", "args": [{"property": "fee"}]}', '!fee'];
        yield 'and' => [
            '{"op": "and", "args": [{"op": "=", "args": [{"property": "a"}, 1]}, {"op": "=", "args": [{"property": "b"}, 2]}]}',
            '(a==1;b==2)',
        ];
        yield 'or inside and' => [
            '{"op": "and", "args": [{"op": "=", "args": [{"property": "a"}, 1]}, {"op": "or", "args": [{"op": "=", "args": [{"property": "b"}, 2]}, {"op": "=", "args": [{"property": "c"}, 3]}]}]}',
            '(a==1;(b==2|c==3))',
        ];
    }

    #[DataProvider('filterProvider')]
    public function testMakesTheNgsiLdQuery(string $json, string $query): void
    {
        $this->assertSame($query, $this->parser->parse($json)->query);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidFilterProvider(): iterable
    {
        yield 'not JSON' => ['isAccessibleForFree==true', 'not valid JSON'];
        yield 'not an object' => ['[1, 2]', 'not valid: /:'];
        yield 'extra key' => ['{"op": "isNull", "args": [{"property": "a"}], "x": 1}', 'not valid: /:'];
        yield 'not' => ['{"op": "not", "args": [{"op": "isNull", "args": [{"property": "a"}]}]}', '/op:'];
        yield 'like' => ['{"op": "like", "args": [{"property": "a"}, "b%"]}', '/op:'];
        yield 'and with one argument' => ['{"op": "and", "args": [{"op": "isNull", "args": [{"property": "a"}]}]}', '/args:'];
        yield 'comparison with one argument' => ['{"op": "=", "args": [{"property": "a"}]}', '/args:'];
        yield 'value first' => ['{"op": "=", "args": [1, {"property": "a"}]}', '/args/0:'];
        yield 'NGSI-LD in the path' => ['{"op": "=", "args": [{"property": "a==1;b"}, 1]}', '/args/0/property:'];
        yield 'quote in a text' => ['{"op": "=", "args": [{"property": "a"}, "x\"|b==\"y"]}', '/args/1:'];
        yield 'backslash in a text' => ['{"op": "=", "args": [{"property": "a"}, "x\\\\"]}', '/args/1:'];
        yield 'new line in a text' => ['{"op": "=", "args": [{"property": "a"}, "x\ny"]}', '/args/1:'];
        yield 'empty text' => ['{"op": "=", "args": [{"property": "a"}, ""]}', '/args/1:'];
        yield 'null' => ['{"op": "=", "args": [{"property": "a"}, null]}', '/args/1:'];
        yield 'in without a list' => ['{"op": "in", "args": [{"property": "a"}, "x"]}', '/args/1:'];
        yield 'invalid filter inside and' => ['{"op": "and", "args": [{"op": "isNull", "args": [{"property": "a"}]}, {"op": "not", "args": []}]}', '/args/1/op:'];
        yield 'in with an empty list' => ['{"op": "in", "args": [{"property": "a"}, []]}', '/args/1:'];
    }

    #[DataProvider('invalidFilterProvider')]
    public function testRejectsAnInvalidFilter(string $json, string $message): void
    {
        $this->expectException(InvalidFilterException::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->parser->parse($json);
    }

    public function testRejectsAFilterThatIsTooLong(): void
    {
        $this->expectException(InvalidFilterException::class);
        $this->expectExceptionMessageIsOrContains('longer than');

        $this->parser->parse(str_repeat(' ', Cql2FilterParser::MAX_LENGTH + 1));
    }

    public function testRejectsAFilterWithTooManyLevels(): void
    {
        $leaf = ['op' => 'isNull', 'args' => [['property' => 'a']]];
        $expression = $leaf;
        for ($level = 0; $level < Cql2FilterParser::MAX_DEPTH; ++$level) {
            $expression = ['op' => 'and', 'args' => [$expression, $leaf]];
        }

        $this->expectException(InvalidFilterException::class);
        $this->expectExceptionMessageIsOrContains('levels');

        $this->parser->parse(json_encode($expression, \JSON_THROW_ON_ERROR));
    }
}
