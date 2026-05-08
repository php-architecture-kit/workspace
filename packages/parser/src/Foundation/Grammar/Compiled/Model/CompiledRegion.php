<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Grammar\Compiled\Model;

use PhpArchitecture\Parser\Foundation\AST\Definition\NodeDefinition;
use PhpArchitecture\Parser\Foundation\Matching\Model\SequenceLibrary;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\PatternLibrary;

final readonly class CompiledRegion
{
    /**
     * @param array<string,CompiledEventSubscriber> $eventSubscribers
     * @param string[] $tags
     */
    public function __construct(
        public string $name,
        public array $eventSubscribers,
        public PatternLibrary $patternLibrary,
        public SequenceLibrary $sequenceLibrary,
        public ?NodeDefinition $definition = null,
        public array $tags = [],
    ) {}
}
