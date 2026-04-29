<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Processing\Model\Ast\Definition;

class EdgeDefinition
{
    public function __construct(
        public readonly string $name,
        public readonly EdgeType $type,
    ) {}
}
