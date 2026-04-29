<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Processing\Model\Ast\Definition;

use Closure;

class ReferenceDefinition
{
    public function __construct(
        public readonly string $name,
        public readonly EdgeDefinition $edge,
        public readonly Closure $toGraphMapper,
    ) {}
}
