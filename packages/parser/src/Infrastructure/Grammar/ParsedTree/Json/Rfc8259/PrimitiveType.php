<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Rfc8259;

enum PrimitiveType: string
{
    case False = "false";
    case Null = "null";
    case True = "true";
    case Number = "number";
    case String = "string";
}
