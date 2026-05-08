<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\AST\Definition;

/**
 * Represents a SequenceNode that is not captured by any AST Definition.
 * This helps identify gaps in AST coverage for lossless parsing.
 */
readonly class MissingDefinition
{
    /**
     * @param string[] $alternatives Alternative names from SequenceNode
     */
    public function __construct(
        public ?string $anchorName,
        public array $alternatives,
        public ?string $sequenceNodeName,
        public string $sourceRuleOrRegion,
    ) {}
}
