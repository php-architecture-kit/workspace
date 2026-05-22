<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Unit\Foundation\Grammar\Definition\Model\Technical;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Technical\TechnicalTokenRule;

#[Group('unit')]
final class TechnicalTokenRuleTest extends TestCase
{
    #[Test]
    public function shouldSetNameThroughConstructorWhenBof(): void
    {
        $name = 'bof';
        $rule = new TechnicalTokenRule($name);

        self::assertSame($name, $rule->name);
    }

    #[Test]
    public function shouldSetNameThroughConstructorWhenEof(): void
    {
        $name = 'eof';
        $rule = new TechnicalTokenRule($name);

        self::assertSame($name, $rule->name);
    }

    #[Test]
    public function shouldSetNameThroughConstructorWhenUnknown(): void
    {
        $name = 'unknown';
        $rule = new TechnicalTokenRule($name);

        self::assertSame($name, $rule->name);
    }

    #[Test]
    public function shouldThrowExceptionWhenConstructorWithInvalidName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must represents only predefined technical tokens: bof, eof, unknown');

        new TechnicalTokenRule('customToken');
    }
}
