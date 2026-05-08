<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Presentation\View\Grammar\DTO;

final class RegionViewData
{
    /**
     * @param string[]                 $tags
     * @param RuleViewData[]           $rules
     * @param MiddlewareViewData[]     $middlewares
     * @param EventSubscriberViewData[] $eventSubscribers
     * @param RegionViewData[]         $nestedRegions
     */
    public function __construct(
        public readonly string $name,
        public readonly string $nodeType,
        public readonly ?string $rootSequence,
        public readonly ?string $opener,
        public readonly ?string $openerEvent,
        public readonly ?string $closer,
        public readonly ?string $closerEvent,
        public readonly ?string $innerGrammar,
        public readonly bool $innerGrammarRetokenize,
        public readonly string $inheritanceInfo,
        public readonly array $tags,
        public readonly array $rules,
        public readonly array $middlewares,
        public readonly array $eventSubscribers,
        public readonly array $nestedRegions,
    ) {}
}
