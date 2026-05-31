<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Grammar\Definition\Middleware\Standard;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Middleware\GrammarMiddleware;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Sequence\NestedSequence;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Sequence\SequenceNode;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Sequence\SequenceRule;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Service\SequenceExtender\SequenceExtender;

final class TriviaSequenceNamingMiddleware implements GrammarMiddleware
{
    public function handle(object $rule): object
    {
        if (!($rule instanceof Rule) || !($rule->definition instanceof SequenceRule)) {
            return $rule;
        }

        $rule->definition = $this->assignTriviaNames($rule->definition);

        return $rule;
    }

    public function hash(): string
    {
        return hash('xxh128', self::class);
    }

    public function method(): string
    {
        return self::ADD_RULE;
    }

    public function priority(): int
    {
        return 0;
    }

    private function assignTriviaNames(SequenceRule $sequence): SequenceRule
    {
        $triviaCount = 0;
        foreach ($sequence->nodes as $node) {
            if ($this->isTriviaNode($node)) {
                $triviaCount++;
            }
        }

        if ($triviaCount > 0) {
            $position = 0;
            $extender = new SequenceExtender();
            $extender
                ->when(fn($n) => $this->isTriviaNode($n))
                ->modify(function (SequenceNode $node, array $ctx) use ($triviaCount, &$position): SequenceNode {
                    $node->anchorName = $this->computeName($position, $triviaCount);
                    $position++;
                    return $node;
                })
                ->always();

            $sequence = $extender->extend($sequence);
        }

        $newNodes = [];
        foreach ($sequence->nodes as $node) {
            if ($node instanceof NestedSequence) {
                $processedAlts = [];
                foreach ($node->alternativeSequences as $altNodes) {
                    $processedAlts[] = $this->assignTriviaNames(new SequenceRule($altNodes))->nodes;
                }
                $newNodes[] = new NestedSequence(
                    $processedAlts,
                    $node->cardinality,
                    $node->isLookahead,
                    $node->isLookbehind,
                    $node->tags,
                    $node->anchorName,
                );
            } else {
                $newNodes[] = $node;
            }
        }

        return new SequenceRule($newNodes);
    }

    private function isTriviaNode(SequenceNode|NestedSequence $node): bool
    {
        return $node instanceof SequenceNode
            && $node->alternatives === ['-']
            && $node->anchorName === null;
    }

    private function computeName(int $position, int $total): string
    {
        return match (true) {
            $total === 1 => 'trivia',
            default => 'trivia' . $position,
        };
    }
}
