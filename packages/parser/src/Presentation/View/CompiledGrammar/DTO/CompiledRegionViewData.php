<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Presentation\View\CompiledGrammar\DTO;

final class CompiledRegionViewData
{
    /**
     * @param string[]                         $tags
     * @param PatternViewData[]                $patterns
     * @param SequenceViewData[]               $sequences
     * @param CompiledEventSubscriberViewData[] $eventSubscribers
     */
    public function __construct(
        public readonly string $name,
        public readonly array $tags,
        public readonly array $patterns,
        public readonly array $sequences,
        public readonly array $eventSubscribers,
    ) {}
}
