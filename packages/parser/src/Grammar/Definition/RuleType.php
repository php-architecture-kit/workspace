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
}
