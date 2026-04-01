<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Definition\Service\SequenceExtender;

use Closure;
use PhpArchitecture\Parser\Grammar\Definition\Model\Sequence\NestedSequence;
use PhpArchitecture\Parser\Grammar\Definition\Model\Sequence\SequenceNode;
use PhpArchitecture\Parser\Grammar\Definition\Model\Sequence\SequenceRule;

class SequenceExtender
{
    /**
     * @var array<array{
     *   matcher: callable(NestedSequence|SequenceNode, int, array): bool,
     *   action: 'add'|'modify'|'remove',
     *   position: 'prev'|'exact'|'next',
     *   callback: callable,
     *   contextMatcher: ?callable
     * }>
     */
    private array $rules = [];

    /**
     * @param callable(NestedSequence|SequenceNode $node, int $index, array $nodes): bool $matcher
     */
    public function when(callable $matcher): SequenceExtenderRule
    {
        return new SequenceExtenderRule($this, $matcher);
    }

    /**
     * @param callable(NestedSequence|SequenceNode $node, int $index, array $nodes): bool $matcher
     * @param 'add'|'modify'|'remove' $action
     * @param 'prev'|'exact'|'next' $position
     * @param callable(NestedSequence|SequenceNode $node, array $context): (NestedSequence|SequenceNode) $callback
     * @param callable(NestedSequence|SequenceNode $contextNode, int $index, array $nodes): bool|null $contextMatcher
     */
    public function addRule(
        callable $matcher,
        string $action,
        string $position,
        callable $callback,
        ?callable $contextMatcher = null
    ): self {
        $this->rules[] = [
            'matcher' => $matcher,
            'action' => $action,
            'position' => $position,
            'callback' => $callback,
            'contextMatcher' => $contextMatcher,
        ];

        return $this;
    }

    public function extend(SequenceRule $sequence): SequenceRule
    {
        $nodes = $sequence->nodes;
        $newNodes = [];
        $skipNext = false;

        foreach ($nodes as $index => $node) {
            if ($skipNext) {
                $skipNext = false;
                continue;
            }

            $prevNode = $index > 0 ? $nodes[$index - 1] : null;
            $nextNode = $index < count($nodes) - 1 ? $nodes[$index + 1] : null;
            $context = ['prev' => $prevNode, 'current' => $node, 'next' => $nextNode];

            $nodeProcessed = false;

            foreach ($this->rules as $rule) {
                $matcher = Closure::fromCallable($rule['matcher']);
                if (!$matcher($node, $index, $nodes)) {
                    continue;
                }

                if ($rule['contextMatcher'] !== null) {
                    $contextNode = match ($rule['position']) {
                        'prev' => $prevNode,
                        'exact' => $prevNode,
                        'next' => $nextNode,
                    };

                    $contextMatcher = Closure::fromCallable($rule['contextMatcher']);
                    if (!$contextMatcher($contextNode, $index, $nodes)) {
                        continue;
                    }
                }

                $callback = Closure::fromCallable($rule['callback']);
                match ($rule['action']) {
                    'add' => match ($rule['position']) {
                        'prev' => $newNodes[] = $callback($node, $context),
                        'exact' => null,
                        'next' => null,
                    },
                    'modify' => match ($rule['position']) {
                        'prev' => null,
                        'exact' => $node = $callback($node, $context),
                        'next' => null,
                    },
                    'remove' => match ($rule['position']) {
                        'prev' => null,
                        'exact' => $nodeProcessed = true,
                        'next' => null,
                    },
                };
            }

            if (!$nodeProcessed) {
                $newNodes[] = $node;
            }

            foreach ($this->rules as $rule) {
                $matcher = Closure::fromCallable($rule['matcher']);
                if (!$matcher($node, $index, $nodes)) {
                    continue;
                }

                if ($rule['contextMatcher'] !== null) {
                    $contextNode = match ($rule['position']) {
                        'prev' => $prevNode,
                        'exact' => $prevNode,
                        'next' => $nextNode,
                    };

                    $contextMatcher = Closure::fromCallable($rule['contextMatcher']);
                    if (!$contextMatcher($contextNode, $index, $nodes)) {
                        continue;
                    }
                }

                if ($rule['action'] === 'add' && $rule['position'] === 'next') {
                    $callback = Closure::fromCallable($rule['callback']);
                    $newNodes[] = $callback($node, $context);
                }
            }
        }

        return new SequenceRule($newNodes);
    }
}
