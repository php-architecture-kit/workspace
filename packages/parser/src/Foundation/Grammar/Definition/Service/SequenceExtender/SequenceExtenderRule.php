<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Grammar\Definition\Service\SequenceExtender;

use Closure;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Sequence\NestedSequence;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Sequence\SequenceNode;

class SequenceExtenderRule
{
    private readonly Closure $matcher;

    /**
     * @param callable(NestedSequence|SequenceNode $node, int $index, array $nodes): bool $matcher
     */
    public function __construct(
        private readonly SequenceExtender $extender,
        callable $matcher,
    ) {
        $this->matcher = Closure::fromCallable($matcher);
    }

    /**
     * @param NestedSequence|SequenceNode|string|callable(NestedSequence|SequenceNode $node, array $context): (NestedSequence|SequenceNode) $node
     */
    public function addPrev(NestedSequence|SequenceNode|string|callable $node): SequenceExtenderRuleContext
    {
        $callback = $this->normalizeCallback($node);
        return new SequenceExtenderRuleContext($this->extender, $this->matcher, 'add', 'prev', $callback);
    }

    /**
     * @param NestedSequence|SequenceNode|string|callable(NestedSequence|SequenceNode $node, array $context): (NestedSequence|SequenceNode) $node
     */
    public function addNext(NestedSequence|SequenceNode|string|callable $node): SequenceExtenderRuleContext
    {
        $callback = $this->normalizeCallback($node);
        return new SequenceExtenderRuleContext($this->extender, $this->matcher, 'add', 'next', $callback);
    }

    /**
     * @param callable(NestedSequence|SequenceNode $node, array $context): (NestedSequence|SequenceNode) $callback
     */
    public function modify(callable $callback): SequenceExtenderRuleContext
    {
        return new SequenceExtenderRuleContext($this->extender, $this->matcher, 'modify', 'exact', $callback);
    }

    public function remove(): SequenceExtenderRuleContext
    {
        return new SequenceExtenderRuleContext(
            $this->extender,
            $this->matcher,
            'remove',
            'exact',
            static fn() => null,
        );
    }

    /**
     * @param NestedSequence|SequenceNode|string|callable(NestedSequence|SequenceNode $node, array $context): (NestedSequence|SequenceNode) $node
     */
    private function normalizeCallback(NestedSequence|SequenceNode|string|callable $node): callable
    {
        if (is_callable($node) && !is_string($node) && !is_array($node)) {
            return $node;
        }

        if (is_string($node)) {
            $node = SequenceNode::fromString($node);
        }

        return static fn() => $node;
    }
}
