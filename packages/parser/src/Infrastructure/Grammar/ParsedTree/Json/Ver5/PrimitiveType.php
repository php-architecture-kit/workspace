<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Ver5;

enum PrimitiveType: string
{
    case False = 'false';
    case Null = 'null';
    case True = 'true';
    case Infinity = 'infinity';
    case Nan = 'nan';
    case Number = 'number';
    case DoubleQuotedString = 'doubleQuotedString';
    case SingleQuotedString = 'singleQuotedString';
}
