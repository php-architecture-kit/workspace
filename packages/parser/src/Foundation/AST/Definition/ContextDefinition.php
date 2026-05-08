<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\AST\Definition;

use Closure;

class ContextDefinition implements AstDefinitionInterface
{
    public function __construct(
        public readonly string $name,
        public readonly Closure $toGraphMapper,
    ) {}
}
