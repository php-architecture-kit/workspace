<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Definition;

enum RuleType
{
    case DynamicToken;
    case Expression;
    case Keyword;
    case Token;

    case Choice;
    case Sequence;

    /** @return RuleType[] */
    public function tokenizationRuleTypes(): array
    {
        return [self::Token, self::Keyword, self::Expression, self::DynamicToken];
    }

    public function isTokenizationRuleType(): bool
    {
        return in_array($this, $this->tokenizationRuleTypes());
    }

    /** @return RuleType[] */
    public function parsingRuleTypes(): array
    {
        return [self::Choice, self::Sequence];
    }

    public function isParsingRuleType(): bool
    {
        return in_array($this, $this->parsingRuleTypes());
    }

    public function isSamePurpose(RuleType $other): bool
    {
        return ($this->isTokenizationRuleType() && $other->isTokenizationRuleType()) ||
            ($this->isParsingRuleType() && $other->isParsingRuleType());
    }
}
