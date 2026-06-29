<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\TreeSchema\Generator\Schema;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\GrammarOrigin;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeOrigin;

/**
 * All data about one node type needed to generate a facade class.
 */
final class NodeSchema
{
    /** @var AttributeSchema[] ordered by attribute index */
    public array $attributes = [];
    public ?GrammarOrigin $origin = null;

    /**
     * True when this node was contributed by a retokenize inner grammar explicitly inserted
     * into the root grammar (e.g. JsonComment in JsonC). Such nodes are always carved out to
     * their own (format/variant) namespace by the router, even when they share the root's
     * format — unlike inherited same-format regions, which stay root-claimed.
     */
    public bool $isInnerGrammarCarveOut = false;

    /** Whether this schema is rendered in the current run (root-claimed or an emitted carve-out). */
    public bool $shouldGenerate = true;

    /**
     * Namespace the facade is emitted into, assigned by the router from the node's
     * (format, variant) under the run's base namespace. Drives both the file's own
     * `namespace` and the local-vs-import decision in the renderer: a reference whose
     * targetNamespace differs from the rendered class's is imported by FQCN.
     */
    public ?string $targetNamespace = null;

    /**
     * FQCN of the shape base class the facade must extend (LeafNode / GroupNode /
     * SequenceNode), captured from the materialized parse node. Null falls back to
     * the generic Node in the renderer.
     */
    public ?string $baseClass = null;

    /**
     * The materialized node's {@see NodeOrigin} (Token/Region/Sequence), captured from
     * the live parse node — required by AbstractNode's constructor in the emitted
     * create(). Distinct from {@see $origin} (GrammarOrigin: format/variant).
     */
    public ?NodeOrigin $nodeOrigin = null;

    public function __construct(
        public readonly string $nodeName,
        public readonly string $className,
    ) {}
}
