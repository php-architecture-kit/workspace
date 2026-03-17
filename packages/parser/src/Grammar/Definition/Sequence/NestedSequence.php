<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Definition\Sequence;

use InvalidArgumentException;
use PhpArchitecture\Parser\Grammar\Definition\Cardinality;

final class NestedSequence
{
    /**
     * @param (NestedSequence|SequenceNode)[][] $alternativeSequences Array of alternative sequences (union)
     * @param string[] $tags
     */
    public function __construct(
        public array $alternativeSequences,
        public Cardinality $cardinality,
        public bool $isLookahead = false,
        public bool $isLookbehind = false,
        public array $tags = [],
    ) {}

    /**
     * @param string $nestedSequence ex.: (?ws member)*, >(ws member), (seq1)|(seq2), (ws member)/t
     */
    public static function fromString(string $nestedSequence): self
    {
        if (!preg_match(
            '/^(?<lookahead>>)?(?<lookbehind><)?(?<optional>\?)?(?<unions>\([a-zA-Z0-9_\-|\(\) \+\*\?<>\[\]\/]+\)(\|\([a-zA-Z0-9_\-|\(\) \+\*\?<>\[\]\/]+\))*)(?<quantifier>[+*])?(?:\/(?<tags>[a-zA-Z]+))?$/',
            $nestedSequence,
            $m,
        )) {
            throw new InvalidArgumentException("Invalid nested sequence: `{$nestedSequence}`");
        }

        $isLookahead = !empty($m['lookahead']);
        $isLookbehind = !empty($m['lookbehind']);
        if ($isLookahead && $isLookbehind) {
            throw new InvalidArgumentException("Invalid nested sequence: `{$nestedSequence}`. Lookahead and lookbehind are not allowed to be used at the same time.");
        }

        $unions = $m['unions'];
        if (empty($unions)) {
            throw new InvalidArgumentException("Invalid nested sequence: `{$nestedSequence}`");
        }

        $alternativeBodies = self::parseUnionBodies($unions);
        $alternativeSequences = [];

        foreach ($alternativeBodies as $body) {
            $nodes = self::parseSequenceNodes($body);
            $nodes = array_map(
                static fn(string $sequenceNode): NestedSequence|SequenceNode => str_contains($sequenceNode, '(')
                    ? NestedSequence::fromString($sequenceNode)
                    : SequenceNode::fromString($sequenceNode),
                $nodes,
            );

            $first = array_key_first($nodes);
            $last = array_key_last($nodes);

            foreach ($nodes as $index => $node) {
                if (in_array($index, [$first, $last])) {
                    continue;
                }

                if ($node->isLookahead) {
                    throw new InvalidArgumentException("Invalid nested sequence. Lookahead is not allowed in the middle of a sequence. It must be the last node. Sequence: `{$nestedSequence}`.");
                }

                if ($node->isLookbehind) {
                    throw new InvalidArgumentException("Invalid nested sequence. Lookbehind is not allowed in the middle of a sequence. It must be the first node. Sequence: `{$nestedSequence}`.");
                }
            }

            $alternativeSequences[] = $nodes;
        }

        $quantifier = $m['quantifier'] ?? '';
        $isAsterisk = $quantifier === '*';
        $optional   = $m['optional'] === '?' || $isAsterisk;
        $oneOrMore  = $quantifier === '+' || $isAsterisk;

        $cardinality = match (true) {
            $optional && $oneOrMore,
            $isAsterisk => Cardinality::ZeroOrMore,
            $optional => Cardinality::ZeroOrOne,
            $oneOrMore => Cardinality::OneOrMore,
            default => Cardinality::ExactlyOne,
        };

        $tags = !empty($m['tags']) ? str_split($m['tags']) : [];

        return new self($alternativeSequences, $cardinality, $isLookahead, $isLookbehind, $tags);
    }

    /**
     * Parse union bodies from format (body1)|(body2)|... 
     * @return string[]
     */
    private static function parseUnionBodies(string $unions): array
    {
        $bodies = [];
        $current = '';
        $depth = 0;

        for ($i = 0; $i < strlen($unions); $i++) {
            $char = $unions[$i];

            if ($char === '(') {
                $depth++;
                if ($depth === 1) {
                    continue;
                }
            } elseif ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    $bodies[] = $current;
                    $current = '';
                    continue;
                }
            } elseif ($char === '|' && $depth === 0) {
                continue;
            }

            $current .= $char;
        }

        return $bodies;
    }

    public function getMinMembersNumber(): int
    {
        $minPerAlternative = [];
        foreach ($this->alternativeSequences as $nodes) {
            $output = 0;
            foreach ($nodes as $node) {
                $output += $node instanceof SequenceNode
                    ? $node->cardinality->min() * ($node->isLookahead || $node->isLookbehind ? 0 : 1)
                    : $node->cardinality->min() * $node->getMinMembersNumber() * ($node->isLookahead || $node->isLookbehind ? 0 : 1);
            }
            $minPerAlternative[] = $output;
        }

        return min($minPerAlternative);
    }

    /**
     * @return string[]
     */
    public function getAllNodeNames(): array
    {
        $names = [];
        foreach ($this->alternativeSequences as $nodes) {
            foreach ($nodes as $node) {
                $names = array_merge($names, $node->getAllNodeNames());
            }
        }

        return array_unique($names);
    }

    /**
     * @return string[]
     */
    public function getFirstValidNodeNodeNames(): array
    {
        $output = [];
        foreach ($this->alternativeSequences as $nodes) {
            foreach ($nodes as $node) {
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
        }

        return array_unique($output);
    }

    public function toString(): string
    {
        $alternatives = array_map(
            static fn(array $nodes): string => '(' . implode(' ', array_map(
                static fn(NestedSequence|SequenceNode $node): string => $node->toString(),
                $nodes,
            )) . ')',
            $this->alternativeSequences,
        );
        $unions = implode('|', $alternatives);

        $prefix = '';
        if ($this->isLookahead) {
            $prefix = '>';
        } elseif ($this->isLookbehind) {
            $prefix = '<';
        }

        $tagsStr = !empty($this->tags) ? '/' . implode('', $this->tags) : '';

        return sprintf(
            '%s%s%s%s%s',
            $prefix,
            $this->cardinality === Cardinality::ZeroOrOne ? '?' : '',
            $unions,
            match ($this->cardinality) {
                Cardinality::OneOrMore => '+',
                Cardinality::ZeroOrMore => '*',
                default => '',
            },
            $tagsStr,
        );
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
}
