<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Unit\Grammar\Definition\Model;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PhpArchitecture\Parser\Grammar\Definition\Model\Cardinality;

#[Group('unit')]
final class CardinalityTest extends TestCase
{
    #[Test]
    public function shouldReturnZeroForMinWhenZeroOrOne(): void
    {
        $cardinality = Cardinality::ZeroOrOne;

        self::assertSame(0, $cardinality->min());
    }

    #[Test]
    public function shouldReturnZeroForMinWhenZeroOrMore(): void
    {
        $cardinality = Cardinality::ZeroOrMore;

        self::assertSame(0, $cardinality->min());
    }

    #[Test]
    public function shouldReturnOneForMinWhenOneOrMore(): void
    {
        $cardinality = Cardinality::OneOrMore;

        self::assertSame(1, $cardinality->min());
    }

    #[Test]
    public function shouldReturnOneForMinWhenExactlyOne(): void
    {
        $cardinality = Cardinality::ExactlyOne;

        self::assertSame(1, $cardinality->min());
    }

    #[Test]
    public function shouldReturnOneForMaxWhenZeroOrOne(): void
    {
        $cardinality = Cardinality::ZeroOrOne;

        self::assertSame(1, $cardinality->max());
    }

    #[Test]
    public function shouldReturnPhpIntMaxForMaxWhenZeroOrMore(): void
    {
        $cardinality = Cardinality::ZeroOrMore;

        self::assertSame(PHP_INT_MAX, $cardinality->max());
    }

    #[Test]
    public function shouldReturnPhpIntMaxForMaxWhenOneOrMore(): void
    {
        $cardinality = Cardinality::OneOrMore;

        self::assertSame(PHP_INT_MAX, $cardinality->max());
    }

    #[Test]
    public function shouldReturnOneForMaxWhenExactlyOne(): void
    {
        $cardinality = Cardinality::ExactlyOne;

        self::assertSame(1, $cardinality->max());
    }
}
