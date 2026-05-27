<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Tokenization\Pratt;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Pratt\PrattRoleDefinition;

final class PrattGrammarDefinition
{
    /** @param array<string, PrattRoleDefinition> $roles */
    public function __construct(
        private readonly array $roles,
    ) {}

    public function getRole(string $name): ?PrattRoleDefinition
    {
        return $this->roles[$name] ?? null;
    }

    public function isAtom(string $name): bool
    {
        return ($this->roles[$name] ?? null)?->isAtom ?? false;
    }

    public function isInfix(string $name): bool
    {
        return ($this->roles[$name] ?? null)?->isInfix ?? false;
    }
}
