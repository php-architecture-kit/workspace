<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Processing\Model\Ast\Definition;

use Closure;
use PhpArchitecture\Parser\Parsing\Model\Node;

class AttributeDefinition
{
    /**
     * @param Closure(Node):mixed $toGraphMapper
     * @param Closure(Node,mixed):void $toTreeMapper
     */
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly bool $optional,
        public readonly ?string $defaultValue,
        public readonly Closure $toGraphMapper,
        public readonly Closure $toTreeMapper,
    ) {}
}
