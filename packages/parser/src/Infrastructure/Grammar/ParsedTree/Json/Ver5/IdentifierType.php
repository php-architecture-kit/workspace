<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Ver5;

enum IdentifierType: string
{
    case NonQuotedIdentifier = 'nonQuotedIdentifier';
    case DoubleQuotedString = 'doubleQuotedString';
    case SingleQuotedString = 'singleQuotedString';
}
