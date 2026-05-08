<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Presentation\View\CompiledGrammar\DTO;

final class SequenceViewData
{
    /**
     * @param string[]               $tags
     * @param SequenceNodeViewData[] $nodes
     */
    public function __construct(
        public readonly string $name,
        public readonly bool $isRoot,
        public readonly int $priority,
        public readonly ?string $nodeType,
        public readonly array $tags,
        public readonly array $nodes,
    ) {}
}
