<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Processing\Model\Matching;

final class SequenceNode
{
    /**
     * @param string[] $alternatives
     * @param string[] $tags
     */
    public function __construct(
        public array $alternatives,
        public int $min,
        public int $max,
        public bool $isLookahead = false,
        public bool $isLookbehind = false,
        public ?string $anchorName = null,
        public array $tags = [],
    ) {}

    /**
     * @return string[]
     */
    public function getAllNodeNames(): array
    {
        return $this->alternatives;
    }
}
