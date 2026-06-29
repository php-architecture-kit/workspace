<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Parsing\Model\Context;

use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;

class NodeContext
{
    /** @var array<string,mixed> */
    public array $nodeContext = [];

    /**
     * Newline-driven formatting state, set by the reformat walk:
     * - breaksLine: this region is laid across multiple lines, so its children
     *   indent one level deeper (counted by ContextStack::indentationLevel()).
     * - beginsLine: a newline precedes this node, so its managed leading
     *   whitespace is indented (`indentUnit × level`) rather than empty.
     */
    public bool $breaksLine = false;
    public bool $beginsLine = false;

    public function __construct(
        public readonly NodeInterface $node,
    ) {}
}
