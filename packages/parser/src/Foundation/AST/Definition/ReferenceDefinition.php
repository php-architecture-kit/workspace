<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\AST\Definition;

use Closure;

class ReferenceDefinition implements AstDefinitionInterface
{
    public function __construct(
        public readonly string $name,
        public readonly EdgeDefinition $edge,
        public readonly Closure $toGraphMapper,
    ) {}
}
