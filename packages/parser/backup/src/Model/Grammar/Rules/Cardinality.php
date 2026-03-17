<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Model\Grammar\Rules;

enum Cardinality: string
{
    case ZeroOrOne = '0..1';
    case ZeroOrMore = '0..*';
    case OneOrMore = '1..*';
    case ExactlyOne = '1';

    public function min(): int
    {
        return match ($this->name) {
            'ZeroOrOne' => 0,
            'ZeroOrMore' => 0,
            'OneOrMore' => 1,
            'ExactlyOne' => 1,
        };
    }

    public function max(): int
    {
        return match ($this->name) {
            'ZeroOrOne' => 1,
            'ZeroOrMore' => PHP_INT_MAX,
            'OneOrMore' => PHP_INT_MAX,
            'ExactlyOne' => 1,
        };
    }
}
