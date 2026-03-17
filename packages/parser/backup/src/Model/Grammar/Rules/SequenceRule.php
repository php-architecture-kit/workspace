<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Model\Grammar\Rules;

use InvalidArgumentException;

final class SequenceRule implements RuleDefinition
{
    /**
     * @param (NestedSequence|SequenceNode)[] $nodes
     */
    public function __construct(
        public readonly array $nodes,
    ) {}

    public static function fromString(string $sequence): self
    {
        self::validateSequence($sequence);

        $nodes = self::parseSequenceNodes($sequence);
        $nodes = array_map(
            static fn(string $sequenceNode): NestedSequence|SequenceNode => str_contains($sequenceNode, '(')
                ? NestedSequence::fromString($sequenceNode)
                : SequenceNode::fromString($sequenceNode),
            $nodes,
        );
        self::validateSequenceNodes($nodes);

        return new self($nodes);
    }

    public function assertSequenceMatchAnchorRequirements(SequenceRule $originalSequence): void
    {
        foreach ($originalSequence->nodes as $index => $node) {
            $anchorNode = $this->nodes[$index];
            if ($node instanceof NestedSequence && ($node->isLookahead || $node->isLookbehind)) {
                if (!($anchorNode instanceof SequenceNode && $anchorNode->toString() === '-')) {
                    throw new InvalidArgumentException("Anchor sequence validation failed. Anchor node `{$anchorNode->toString()}` doesn't match the original node `{$node->toString()}` grammar node type requirements. Lookahead and lookbehind nested sequences requires the single anchor node named `-`.");
                }

                continue;
            }

            if (
                ($node instanceof SequenceNode && !$anchorNode instanceof SequenceNode) ||
                ($node instanceof NestedSequence && !$anchorNode instanceof NestedSequence)
            ) {
                throw new InvalidArgumentException("Anchor sequence validation failed. Anchor node `{$anchorNode->toString()}` doesn't match original node `{$node->toString()}` grammar node type (nested sequence or sequence node).");
            }

            $anchorNode->assertSequenceMatchAnchorRequirements($node);
        }
    }

    /**
     * @return string[]
     */
    public function getAllNodeNames(): array
    {
        return array_unique(array_merge(...array_map(
            static fn(NestedSequence|SequenceNode $node): array => $node->getAllNodeNames(),
            $this->nodes,
        )));
    }

    /**
     * @return string[]
     */
    public function getFirstValidNodeNodeNames(): array
    {
        $output = [];
        foreach ($this->nodes as $node) {
            if ($node->isLookbehind) {
                continue;
            }

            if ($node instanceof SequenceNode) {
                $output = array_merge($output, $node->alternatives);
            }

            if ($node instanceof NestedSequence) {
                $output = array_merge($output, $node->getFirstValidNodeNodeNames());
            }

            if ($node->cardinality->min() >= 1) {
                break;
            }
        }

        return array_unique($output);
    }

    public function toString(): string
    {
        $sequence = implode(' ', array_map(static fn(NestedSequence|SequenceNode $node): string => $node->toString(), $this->nodes));

        return $sequence;
    }

    /**
     * Parse sequence nodes respecting parentheses boundaries
     * @return string[]
     */
    private static function parseSequenceNodes(string $sequence): array
    {
        $nodes = [];
        $current = '';
        $depth = 0;

        for ($i = 0; $i < strlen($sequence); $i++) {
            $char = $sequence[$i];

            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
            }

            if ($char === ' ' && $depth === 0) {
                if ($current !== '') {
                    $nodes[] = $current;
                    $current = '';
                }
            } else {
                $current .= $char;
            }
        }

        if ($current !== '') {
            $nodes[] = $current;
        }

        return $nodes;
    }

    private static function validateSequence(string $sequence): void
    {
        if ($sequence === '') {
            throw new InvalidArgumentException("Sequence can't be empty.");
        }

        foreach (['+|', '*|', '|?', '|<', '|>', '||'] as $forbiddenSubstring) {
            if (str_contains($sequence, $forbiddenSubstring)) {
                throw new InvalidArgumentException("Sequence contains forbidden substrings. Found: `{$forbiddenSubstring}`. Sequence: `{$sequence}`");
            }
        }
    }

    /**
     * @param array<SequenceNode|NestedSequence> $nodes
     */
    private static function validateSequenceNodes(array $nodes): void
    {
        if (empty($nodes)) {
            throw new InvalidArgumentException("Sequence can't be empty.");
        }

        $first = array_key_first($nodes);
        $last = array_key_last($nodes);
        $minTokensFound = 0;

        foreach ($nodes as $index => $node) {
            if ($node instanceof NestedSequence && count($node->alternativeSequences) === 0) {
                throw new InvalidArgumentException("Nested sequence can't be empty. Sequence: `" . self::buildSequenceString($nodes) . "`");
            }

            $minTokensFound += $node instanceof SequenceNode
                ? $node->cardinality->min() * ($node->isLookahead || $node->isLookbehind ? 0 : 1)
                : $node->cardinality->min() * $node->getMinMembersNumber() * ($node->isLookahead || $node->isLookbehind ? 0 : 1);

            if (in_array($index, [$first, $last])) {
                continue;
            }

            if ($node->isLookahead) {
                throw new InvalidArgumentException("Lookahead is not allowed in the middle of a sequence. It must be the last node. Sequence: `" . self::buildSequenceString($nodes) . "`");
            }

            if ($node->isLookbehind) {
                throw new InvalidArgumentException("Lookbehind is not allowed in the middle of a sequence. It must be the first node. Sequence: `" . self::buildSequenceString($nodes) . "`");
            }
        }

        if ($minTokensFound === 0) {
            throw new InvalidArgumentException("Sequence has minimum members count equal to 0 what is forbidden. Sequence: `" . self::buildSequenceString($nodes) . "`");
        }
    }

    private static function buildSequenceString(array $nodes): string
    {
        return implode(' ', array_map(static fn(SequenceNode|NestedSequence $node) => $node->toString(), $nodes));
    }
}
