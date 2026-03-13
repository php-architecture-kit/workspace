<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Model\Grammar;

enum RuleType: string
{
    public const PURPOSE_LEXER = 'lexer';
    public const PURPOSE_PARSER = 'parser';

    case DynamicToken = 'dynamic_token';
    case Expression = 'expression';
    case Keyword = 'keyword';
    case Token = 'token';

    case Choice = 'choice';
    case Sequence = 'sequence';

    public function getPurpose(): string
    {
        return match ($this) {
            self::Token, self::Keyword, self::Expression, self::DynamicToken => self::PURPOSE_LEXER,
            self::Sequence, self::Choice => self::PURPOSE_PARSER,
        };
    }

    public function isLexerRule(): bool
    {
        return $this->getPurpose() === self::PURPOSE_LEXER;
    }

    public function isParserRule(): bool
    {
        return $this->getPurpose() === self::PURPOSE_PARSER;
    }

    public function isSamePurpose(self $other): bool
    {
        return $this->getPurpose() === $other->getPurpose();
    }
}
