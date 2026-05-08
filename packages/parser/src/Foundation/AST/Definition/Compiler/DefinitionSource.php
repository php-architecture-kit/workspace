<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\AST\Definition\Compiler;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Definition;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Sequence\SequenceRule;

/**
 * Wrapper for Definition with its source context needed for compilation.
 */
readonly class DefinitionSource
{
    public function __construct(
        public Definition $definition,
        public string $sourceName,
        public ?SequenceRule $rootSequence,
    ) {}
}
