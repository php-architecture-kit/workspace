<?php

declare(strict_types=1);

namespace Tests\PhpArchitecture\Graph\Unit\EdgeWeight;

use PhpArchitecture\Graph\EdgeWeight\Weight;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class WeightTest extends TestCase
{
    #[Test]
    public function constructorStoresKeyAndValue(): void
    {
        $weight = new Weight('cost', 12.5);

        $this->assertSame('cost', $weight->key);
        $this->assertSame(12.5, $weight->value);
    }

    #[Test]
    public function constructorThrowsForEmptyKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Weight('', 1.0);
    }

    #[Test]
    public function constructorThrowsForNonFiniteValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Weight('cost', INF);
    }
}
