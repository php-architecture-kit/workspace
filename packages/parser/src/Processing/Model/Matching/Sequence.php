<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Processing\Model\Matching;

final readonly class Sequence
{
    /**
     * @param (NestedSequence|SequenceNode)[] $nodes
     */
    public function __construct(
        public string $name,
        public array $nodes,
        public int $priority,
        public array $tags,
    ) {}

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

            if ($node->min >= 1) {
                break;
            }
        }

        return array_unique($output);
    }
}
