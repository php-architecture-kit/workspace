<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar;

use PhpArchitecture\Parser\Parsing\Event\Contract\ParsingEvent;
use PhpArchitecture\Parser\Parsing\Event\Contract\ParsingEventListener;
use PhpArchitecture\Parser\Tokenization\Event\Contract\TokenizationEvent;
use PhpArchitecture\Parser\Tokenization\Event\Contract\TokenizationEventListener;

class EventSubscriber
{
    /** 
     * @param class-string<TokenizationEvent|ParsingEvent> $eventClassName
     * @param class-string<TokenizationEvent|ParsingEvent> $delayUntilEvent
     */
    public function __construct(
        public readonly string $eventClassName,
        public readonly TokenizationEventListener|ParsingEventListener $listener,
        public readonly ?string $onlyForRuleName = null,
        public readonly ?string $delayUntilEvent = null,
        public readonly int $priority = 0,
    ) {}
}
