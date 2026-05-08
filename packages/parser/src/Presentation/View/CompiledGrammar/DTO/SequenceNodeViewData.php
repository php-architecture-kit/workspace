<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Presentation\View\CompiledGrammar\DTO;

final class SequenceNodeViewData
{
    public const TYPE_SIMPLE = 'simple';
    public const TYPE_NESTED = 'nested';

    /**
     * @param string[]               $alternatives  non-empty for TYPE_SIMPLE
     * @param string[]               $tags
     * @param string|null            $nodeType
     * @param SequenceNodeViewData[][] $alternatives2  alternatives for TYPE_NESTED (list of node lists)
     */
    public function __construct(
        public readonly string $type,
        public readonly int $min,
        public readonly int $max,
        public readonly bool $isLookahead,
        public readonly bool $isLookbehind,
        public readonly array $alternatives,
        public readonly array $tags,
        public readonly ?string $nodeType,
        public readonly array $nestedAlternatives,
    ) {}
}
