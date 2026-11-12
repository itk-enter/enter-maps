<?php

declare(strict_types=1);

namespace App\Tests\Unit\MapConfig;

use App\MapConfig\FilterTarget;
use PHPUnit\Framework\TestCase;

final class FilterTargetTest extends TestCase
{
    public function testValueForGivesTheValueOfTheCode(): void
    {
        $target = new FilterTarget('toilets', 'isAccessibleForFree', ['free' => true]);

        $this->assertTrue($target->valueFor('free'));
    }

    public function testValueForGivesTheCodeIfItHasNoValue(): void
    {
        $target = new FilterTarget('toilets', 'wheelchairAccessible', []);

        $this->assertSame('limited', $target->valueFor('limited'));
    }

    public function testValueForAcceptsACodeThatPhpMadeAnInteger(): void
    {
        // json_decode() makes the key "1" the integer 1.
        $target = new FilterTarget('parking', 'floor', json_decode('{"1": 0}', true, 512, \JSON_THROW_ON_ERROR));

        $this->assertSame(0, $target->valueFor(1));
        $this->assertSame('2', $target->valueFor(2));
    }
}
