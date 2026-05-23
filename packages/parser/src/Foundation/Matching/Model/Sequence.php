<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Matching\Model;

use PhpArchitecture\Parser\Foundation\Shared\Meta\MetaInterface;
use PhpArchitecture\Parser\Foundation\Shared\Meta\MetaTrait;
use PhpArchitecture\Parser\Foundation\Shared\Tags\TagsTrait;

final class Sequence implements MetaInterface
{
    use MetaTrait;
    use TagsTrait;

    /**
     * @param (NestedSequence|SequenceNode)[] $nodes
     * @param array<string,mixed> $meta
     * @param string[] $tags
     */
    public function __construct(
        public string $name,
        public array $nodes,
        public int $priority,
        array $meta = [],
        array $tags = [],
    ) {
        $this->meta = $meta;
        $this->tags = $tags;
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

            if ($node instanceof SequenceNode && $node->isNegation) {
                if ($node->min >= 1) {
                    return [];
                }
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

        return array_unique($output);
    }
}
