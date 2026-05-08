<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Unit\Grammar\Definition\Model;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\RuleType;

#[Group('unit')]
final class RuleTypeTest extends TestCase
{
    #[Test]
    public function shouldReturnCorrectTokenizationRuleTypes(): void
    {
        $expected = [RuleType::Token, RuleType::Keyword, RuleType::Expression];

        self::assertEquals($expected, RuleType::Token->tokenizationRuleTypes());
    }

    #[Test]
    public function shouldReturnTrueForIsTokenizationRuleTypeWhenToken(): void
    {
        self::assertTrue(RuleType::Token->isTokenizationRuleType());
    }

    #[Test]
    public function shouldReturnTrueForIsTokenizationRuleTypeWhenKeyword(): void
    {
        self::assertTrue(RuleType::Keyword->isTokenizationRuleType());
    }

    #[Test]
    public function shouldReturnTrueForIsTokenizationRuleTypeWhenExpression(): void
    {
        self::assertTrue(RuleType::Expression->isTokenizationRuleType());
    }

    #[Test]
    public function shouldReturnFalseForIsTokenizationRuleTypeWhenChoice(): void
    {
        self::assertFalse(RuleType::Choice->isTokenizationRuleType());
    }

    #[Test]
    public function shouldReturnCorrectParsingRuleTypes(): void
    {
        $expected = [RuleType::Choice, RuleType::Sequence];

        self::assertEquals($expected, RuleType::Choice->parsingRuleTypes());
    }

    #[Test]
    public function shouldReturnTrueForIsParsingRuleTypeWhenChoice(): void
    {
        self::assertTrue(RuleType::Choice->isParsingRuleType());
    }

    #[Test]
    public function shouldReturnTrueForIsParsingRuleTypeWhenSequence(): void
    {
        self::assertTrue(RuleType::Sequence->isParsingRuleType());
    }

    #[Test]
    public function shouldReturnFalseForIsParsingRuleTypeWhenToken(): void
    {
        self::assertFalse(RuleType::Token->isParsingRuleType());
    }

    #[Test]
    public function shouldReturnTrueForIsSamePurposeWhenBothAreTokenizationTypes(): void
    {
        self::assertTrue(RuleType::Token->isSamePurpose(RuleType::Keyword));
    }

    #[Test]
    public function shouldReturnTrueForIsSamePurposeWhenBothAreParsingTypes(): void
    {
        self::assertTrue(RuleType::Choice->isSamePurpose(RuleType::Sequence));
    }

    #[Test]
    public function shouldReturnFalseForIsSamePurposeWhenDifferentPurpose(): void
    {
        self::assertFalse(RuleType::Token->isSamePurpose(RuleType::Choice));
    }
}
