<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\TreeSchema\Model\Json\C;

enum PrimitiveType: string
{
    case String = 'string';
    case Number = 'number';
    case True = 'true';
    case False = 'false';
    case Null = 'null';
}
