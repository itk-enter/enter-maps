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
}
