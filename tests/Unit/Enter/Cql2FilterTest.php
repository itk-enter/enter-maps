<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enter;

use App\Enter\Cql2Filter;
use App\Enter\InvalidFilterException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class Cql2FilterTest extends TestCase
{
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
        $this->assertSame($query, Cql2Filter::fromJson($json)->query);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidFilterProvider(): iterable
    {
        yield 'not JSON' => ['isAccessibleForFree==true', 'not valid JSON'];
        yield 'not an object' => ['[1, 2]', 'only "op" and "args"'];
        yield 'extra key' => ['{"op": "isNull", "args": [{"property": "a"}], "x": 1}', 'only "op" and "args"'];
        yield 'not' => ['{"op": "not", "args": [{"op": "isNull", "args": [{"property": "a"}]}]}', 'does not support the operator "not"'];
        yield 'like' => ['{"op": "like", "args": [{"property": "a"}, "b%"]}', 'does not support the operator "like"'];
        yield 'and with one argument' => ['{"op": "and", "args": [{"op": "isNull", "args": [{"property": "a"}]}]}', 'two or more arguments'];
        yield 'comparison with one argument' => ['{"op": "=", "args": [{"property": "a"}]}', 'must have 2 arguments'];
        yield 'value first' => ['{"op": "=", "args": [1, {"property": "a"}]}', 'first argument'];
        yield 'NGSI-LD in the path' => ['{"op": "=", "args": [{"property": "a==1;b"}, 1]}', 'first argument'];
        yield 'quote in a text' => ['{"op": "=", "args": [{"property": "a"}, "x\"|b==\"y"]}', 'without quotes'];
        yield 'backslash in a text' => ['{"op": "=", "args": [{"property": "a"}, "x\\\\"]}', 'without quotes'];
        yield 'new line in a text' => ['{"op": "=", "args": [{"property": "a"}, "x\ny"]}', 'without quotes'];
        yield 'empty text' => ['{"op": "=", "args": [{"property": "a"}, ""]}', 'without quotes'];
        yield 'null' => ['{"op": "=", "args": [{"property": "a"}, null]}', 'must be a number'];
        yield 'in without a list' => ['{"op": "in", "args": [{"property": "a"}, "x"]}', 'list of values'];
        yield 'in with an empty list' => ['{"op": "in", "args": [{"property": "a"}, []]}', 'list of values'];
    }

    #[DataProvider('invalidFilterProvider')]
    public function testRejectsAnInvalidFilter(string $json, string $message): void
    {
        $this->expectException(InvalidFilterException::class);
        $this->expectExceptionMessageIsOrContains($message);

        Cql2Filter::fromJson($json);
    }

    public function testRejectsAFilterThatIsTooLong(): void
    {
        $this->expectException(InvalidFilterException::class);
        $this->expectExceptionMessageIsOrContains('longer than');

        Cql2Filter::fromJson(str_repeat(' ', Cql2Filter::MAX_LENGTH + 1));
    }

    public function testRejectsAFilterWithTooManyLevels(): void
    {
        $leaf = ['op' => 'isNull', 'args' => [['property' => 'a']]];
        $expression = $leaf;
        for ($level = 0; $level < Cql2Filter::MAX_DEPTH; ++$level) {
            $expression = ['op' => 'and', 'args' => [$expression, $leaf]];
        }

        $this->expectException(InvalidFilterException::class);
        $this->expectExceptionMessageIsOrContains('levels');

        Cql2Filter::fromJson(json_encode($expression, \JSON_THROW_ON_ERROR));
    }
}
