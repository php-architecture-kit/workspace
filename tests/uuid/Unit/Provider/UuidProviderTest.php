<?php

declare(strict_types=1);

namespace Tests\PhpArchitecture\Uuid\Unit\Provider;

use PhpArchitecture\Uuid\Provider\UuidProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class UuidProviderTest extends TestCase
{
    #[Test]
    public function supportMatrixReturnsZeroForAllMethodsByDefault(): void
    {
        $matrix = UuidProvider::supportMatrix();

        $this->assertSame(0.0, $matrix['v1']);
        $this->assertSame(0.0, $matrix['v3']);
        $this->assertSame(0.0, $matrix['v4']);
        $this->assertSame(0.0, $matrix['v5']);
        $this->assertSame(0.0, $matrix['v6']);
        $this->assertSame(0.0, $matrix['v7']);
        $this->assertSame(0.0, $matrix['v8']);
        $this->assertSame(0.0, $matrix['validate']);
    }

    #[Test]
    public function supportMatrixContainsAllRequiredKeys(): void
    {
        $matrix = UuidProvider::supportMatrix();

        $this->assertArrayHasKey('v1', $matrix);
        $this->assertArrayHasKey('v3', $matrix);
        $this->assertArrayHasKey('v4', $matrix);
        $this->assertArrayHasKey('v5', $matrix);
        $this->assertArrayHasKey('v6', $matrix);
        $this->assertArrayHasKey('v7', $matrix);
        $this->assertArrayHasKey('v8', $matrix);
        $this->assertArrayHasKey('validate', $matrix);
    }
}
