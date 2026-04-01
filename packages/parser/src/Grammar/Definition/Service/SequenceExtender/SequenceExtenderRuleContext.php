<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Definition\Service\SequenceExtender;

use Closure;
use PhpArchitecture\Parser\Grammar\Definition\Model\Sequence\NestedSequence;
use PhpArchitecture\Parser\Grammar\Definition\Model\Sequence\SequenceNode;

class SequenceExtenderRuleContext
{
    private readonly Closure $matcher;
    private readonly Closure $callback;
    private bool $registered = false;

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
     * @param callable(NestedSequence|SequenceNode $contextNode, int $index, array $nodes): bool $contextMatcher
     */
    public function which(callable $contextMatcher): SequenceExtender
    {
        if ($this->registered) {
            throw new \LogicException('Rule already registered. Call which() before registerRule().');
        }

        $this->extender->addRule(
            $this->matcher,
            $this->action,
            $this->position,
            $this->callback,
            $contextMatcher
        );
        $this->registered = true;

        return $this->extender;
    }

    public function always(): SequenceExtender
    {
        if ($this->registered) {
            throw new \LogicException('Rule already registered.');
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
            null
        );
        $this->registered = true;
    }
}
