<?php

declare(strict_types=1);

namespace App\Tests\Unit\MapConfig;

use App\MapConfig\OpenFilter;
use PHPUnit\Framework\TestCase;

final class OpenFilterTest extends TestCase
{
    public function testIsOffAtTheStartByDefault(): void
    {
        $condition = ['op' => 'isNull', 'args' => [['property' => 'address']]];

        $this->assertEquals(new OpenFilter('Uden adresse', $condition, false), OpenFilter::fromArray(['label' => 'Uden adresse', 'condition' => $condition]));
        $this->assertTrue(OpenFilter::fromArray(['label' => 'Uden adresse', 'condition' => $condition, 'checked' => true])->checked);
    }
}
