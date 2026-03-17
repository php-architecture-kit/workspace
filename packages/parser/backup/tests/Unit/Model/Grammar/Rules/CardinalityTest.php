<?php

declare(strict_types=1);

namespace Tests\PhpArchitecture\Parser\Unit\Model\Grammar\Rules;

use PhpArchitecture\Parser\Model\Grammar\Rules\Cardinality;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CardinalityTest extends TestCase
{
    #[Test]
    public function zeroOrOneHasCorrectValue(): void
    {
        $this->assertSame('0..1', Cardinality::ZeroOrOne->value);
    }

    #[Test]
    public function zeroOrMoreHasCorrectValue(): void
    {
        $this->assertSame('0..*', Cardinality::ZeroOrMore->value);
    }

    #[Test]
    public function oneOrMoreHasCorrectValue(): void
    {
        $this->assertSame('1..*', Cardinality::OneOrMore->value);
    }

    #[Test]
    public function exactlyOneHasCorrectValue(): void
    {
        $this->assertSame('1', Cardinality::ExactlyOne->value);
    }

    #[Test]
    #[DataProvider('provideCardinalityMinValues')]
    public function minReturnsCorrectValue(Cardinality $cardinality, int $expectedMin): void
    {
        $this->assertSame($expectedMin, $cardinality->min());
    }

    #[Test]
    #[DataProvider('provideCardinalityMaxValues')]
    public function maxReturnsCorrectValue(Cardinality $cardinality, int $expectedMax): void
    {
        $this->assertSame($expectedMax, $cardinality->max());
    }

    #[Test]
    public function zeroOrOneAllowsZeroOccurrences(): void
    {
        $cardinality = Cardinality::ZeroOrOne;
        
        $this->assertSame(0, $cardinality->min());
        $this->assertSame(1, $cardinality->max());
    }

    #[Test]
    public function zeroOrMoreAllowsUnlimitedOccurrences(): void
    {
        $cardinality = Cardinality::ZeroOrMore;
        
        $this->assertSame(0, $cardinality->min());
        $this->assertSame(PHP_INT_MAX, $cardinality->max());
    }

    #[Test]
    public function oneOrMoreRequiresAtLeastOne(): void
    {
        $cardinality = Cardinality::OneOrMore;
        
        $this->assertSame(1, $cardinality->min());
        $this->assertSame(PHP_INT_MAX, $cardinality->max());
    }

    #[Test]
    public function exactlyOneRequiresExactlyOne(): void
    {
        $cardinality = Cardinality::ExactlyOne;
        
        $this->assertSame(1, $cardinality->min());
        $this->assertSame(1, $cardinality->max());
    }

    #[Test]
    public function allCasesAreAvailable(): void
    {
        $cases = Cardinality::cases();
        
        $this->assertCount(4, $cases);
        $this->assertContains(Cardinality::ZeroOrOne, $cases);
        $this->assertContains(Cardinality::ZeroOrMore, $cases);
        $this->assertContains(Cardinality::OneOrMore, $cases);
        $this->assertContains(Cardinality::ExactlyOne, $cases);
    }

    public static function provideCardinalityMinValues(): array
    {
        return [
            'ZeroOrOne has min 0' => [Cardinality::ZeroOrOne, 0],
            'ZeroOrMore has min 0' => [Cardinality::ZeroOrMore, 0],
            'OneOrMore has min 1' => [Cardinality::OneOrMore, 1],
            'ExactlyOne has min 1' => [Cardinality::ExactlyOne, 1],
        ];
    }

    public static function provideCardinalityMaxValues(): array
    {
        return [
            'ZeroOrOne has max 1' => [Cardinality::ZeroOrOne, 1],
            'ZeroOrMore has max PHP_INT_MAX' => [Cardinality::ZeroOrMore, PHP_INT_MAX],
            'OneOrMore has max PHP_INT_MAX' => [Cardinality::OneOrMore, PHP_INT_MAX],
            'ExactlyOne has max 1' => [Cardinality::ExactlyOne, 1],
        ];
    }
}
