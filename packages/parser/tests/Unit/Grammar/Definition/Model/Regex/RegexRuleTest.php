<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Unit\Grammar\Definition\Model\Regex;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PhpArchitecture\Parser\Grammar\Definition\Model\Regex\RegexRule;

#[Group('unit')]
final class RegexRuleTest extends TestCase
{
    #[Test]
    public function shouldSetRegexThroughConstructor(): void
    {
        $regex = '~\Gtest~u';
        $rule = new RegexRule($regex);

        self::assertSame($regex, $rule->regex);
    }

    #[Test]
    public function shouldCreateRegexWithCaseSensitiveFlagWhenFromStringWithCaseSensitive(): void
    {
        $rule = RegexRule::fromString('test', true);

        self::assertSame('~\Gtest~u', $rule->regex);
    }

    #[Test]
    public function shouldCreateRegexWithCaseInsensitiveFlagWhenFromStringWithCaseInsensitive(): void
    {
        $rule = RegexRule::fromString('test', false);

        self::assertSame('~\Gtest~ui', $rule->regex);
    }

    #[Test]
    public function shouldAddPrefixAndSuffixWhenFromString(): void
    {
        $rule = RegexRule::fromString('pattern');

        self::assertStringStartsWith('~\G', $rule->regex);
        self::assertStringEndsWith('~u', $rule->regex);
    }
}
