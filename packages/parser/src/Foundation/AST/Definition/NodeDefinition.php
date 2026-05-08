<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\AST\Definition;

class NodeDefinition
{
    /** 
     * @param AttributeDefinition[] $attributes
     * @param ChildDefinition[] $children
     * @param ContextDefinition[] $contexts
     * @param ReferenceDefinition[] $references
     */
    public function __construct(
        public readonly string $name,
        public readonly array $attributes,
        public readonly array $children,
        public readonly array $contexts,
        public readonly FormatDefinition $formats,
        public readonly array $references,
    ) {}
}
