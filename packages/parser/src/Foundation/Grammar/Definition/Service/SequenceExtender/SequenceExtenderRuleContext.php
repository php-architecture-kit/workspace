<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Grammar\Definition\Service\SequenceExtender;

use Closure;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Sequence\NestedSequence;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Sequence\SequenceNode;
use LogicException;

class SequenceExtenderRuleContext
{
    private readonly Closure $matcher;
    private readonly Closure $callback;
    private bool $registered = false;
    private bool $recursive = false;

    public function __destruct()
    {
        if (!$this->registered) {
            $this->registerRule();
        }
    }

    /**
     * @param callable(NestedSequence|SequenceNode $node, int $index, array $nodes): bool $matcher
     * @param 'add'|'modify'|'remove' $action
     * @param 'prev'|'exact'|'next' $position
     * @param callable(NestedSequence|SequenceNode $node, array $context): (NestedSequence|SequenceNode) $callback
     */
    public function __construct(
        private readonly SequenceExtender $extender,
        callable $matcher,
        private readonly string $action,
        private readonly string $position,
        callable $callback,
    ) {
        $this->matcher = Closure::fromCallable($matcher);
        $this->callback = Closure::fromCallable($callback);
    }

    /**
     * Enable recursive processing of NestedSequence nodes.
     * When enabled, the rule will be applied to nodes inside NestedSequence as well.
     */
    public function applyRecursively(): self
    {
        if ($this->registered) {
            throw new LogicException('Rule already registered. Call applyRecursively() before which() or always().');
        }

        $this->recursive = true;
        return $this;
    }

    /**
     * @param callable(NestedSequence|SequenceNode $contextNode, int $index, array $nodes): bool $contextMatcher
     */
    public function which(callable $contextMatcher): SequenceExtender
    {
        if ($this->registered) {
            throw new LogicException('Rule already registered. Call which() before registerRule().');
        }

        $this->extender->addRule(
            $this->matcher,
            $this->action,
            $this->position,
            $this->callback,
            $contextMatcher,
            $this->recursive,
        );
        $this->registered = true;

        return $this->extender;
    }

    public function always(): SequenceExtender
    {
        if ($this->registered) {
            throw new LogicException('Rule already registered.');
        }

        $this->registerRule();
        return $this->extender;
    }

    /**
     * @internal
     */
    private function registerRule(): void
    {
        $this->extender->addRule(
            $this->matcher,
            $this->action,
            $this->position,
            $this->callback,
            null,
            $this->recursive,
        );
        $this->registered = true;
    }
}
