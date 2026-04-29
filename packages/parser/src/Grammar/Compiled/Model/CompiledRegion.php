<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Compiled\Model;

use PhpArchitecture\Parser\Processing\Model\Ast\Definition\NodeDefinition;
use PhpArchitecture\Parser\Processing\Model\Matching\SequenceLibrary;
use PhpArchitecture\Parser\Processing\Model\Tokenization\PatternLibrary;

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
