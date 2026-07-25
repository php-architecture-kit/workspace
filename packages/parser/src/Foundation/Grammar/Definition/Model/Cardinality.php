<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Grammar\Definition\Model;

enum Cardinality: string
{
    case ZeroOrOne = '0..1';
    case ZeroOrMore = '0..*';
    case OneOrMore = '1..*';
    case ExactlyOne = '1';

    /**
     * Inverse of {@see min()}/{@see max()}: reconstructs the cardinality from concrete
     * bounds (e.g. the min/max carried by a compiled Matching sequence node).
     */
    public static function fromBounds(int $min, int $max): self
    {
        return match (true) {
            $min === 0 && $max === 1 => self::ZeroOrOne,
            $min === 0 => self::ZeroOrMore,
            $max > 1 => self::OneOrMore,
            default => self::ExactlyOne,
        };
    }

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
