<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Grammar\Definition\Pratt;

final class PrattRoleDefinition
{
    public function __construct(
        public readonly bool $isAtom,
        public readonly bool $isInfix,
        public readonly int $bindingPower = 0,
        public readonly bool $rightAssociative = false,
    ) {}

    public static function atom(): self
    {
        return new self(isAtom: true, isInfix: false);
    }

    public static function infix(int $bindingPower, bool $rightAssociative = false): self
    {
        return new self(isAtom: false, isInfix: true, bindingPower: $bindingPower, rightAssociative: $rightAssociative);
    }
}
