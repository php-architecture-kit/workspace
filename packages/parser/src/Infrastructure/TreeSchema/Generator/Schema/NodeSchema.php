<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\TreeSchema\Generator\Schema;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\GrammarOrigin;

/**
 * All data about one node type needed to generate a facade class.
 */
final class NodeSchema
{
    /** @var AttributeSchema[] ordered by attribute index */
    public array $attributes = [];
    public ?GrammarOrigin $origin = null;
    public bool $shouldGenerate = true;

    /** FQCN for imported nodes (different format) */
    public ?string $importFqcn = null;

    /**
     * FQCN of the shape base class the facade must extend (LeafNode / GroupNode /
     * SequenceNode), captured from the materialized parse node. Null falls back to
     * the generic Node in the renderer.
     */
    public ?string $baseClass = null;

    public function __construct(
        public readonly string $nodeName,
        public readonly string $className,
    ) {}
}
