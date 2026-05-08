<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Matching\Model;

use PhpArchitecture\Parser\Foundation\Shared\Meta\MetaInterface;
use PhpArchitecture\Parser\Foundation\Shared\Meta\MetaTrait;
use PhpArchitecture\Parser\Foundation\Shared\Tags\TagsTrait;

final class NestedSequence implements MetaInterface
{
    use MetaTrait;
    use TagsTrait;

    /**
     * @param (NestedSequence|SequenceNode)[][] $alternativeSequences Array of alternative sequences (union)
     * @param array<string,mixed> $meta
     * @param string[] $tags
     */
    public function __construct(
        public array $alternativeSequences,
        public int $min,
        public int $max,
        public bool $isLookahead = false,
        public bool $isLookbehind = false,
        array $meta = [],
        array $tags = [],
    ) {
        $this->meta = $meta;
        $this->tags = $tags;
    }

    public function getMinMembersNumber(): int
    {
        $minPerAlternative = [];
        foreach ($this->alternativeSequences as $nodes) {
            $output = 0;
            foreach ($nodes as $node) {
                $output += $node instanceof SequenceNode
                    ? $node->min * ($node->isLookahead || $node->isLookbehind ? 0 : 1)
                    : $node->min * $node->getMinMembersNumber() * ($node->isLookahead || $node->isLookbehind ? 0 : 1);
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

                if ($node->min >= 1) {
                    break;
                }
            }
        }

        return array_unique($output);
    }
}
