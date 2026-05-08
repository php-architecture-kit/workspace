<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Grammar\Definition;

use PhpArchitecture\Parser\Foundation\AST\Definition\AstDefinitionInterface;

class Definition
{
    public function __construct(
        public private(set) string $name,
    ) {
    }

    public function add(AstDefinitionInterface ...$definitions): void
    {
        // TODO: Implement add() method.
    }
}
