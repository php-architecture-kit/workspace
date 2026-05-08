<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\AST\Definition;

class FormatDefinition implements AstDefinitionInterface
{
    public function __construct(
        public readonly string $name,
    ) {}
}
