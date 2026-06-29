<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\C;

enum PrimitiveType: string
{
    case False = 'false';
    case Null = 'null';
    case True = 'true';
    case Number = 'number';
    case DoubleQuotedString = 'doubleQuotedString';
}
