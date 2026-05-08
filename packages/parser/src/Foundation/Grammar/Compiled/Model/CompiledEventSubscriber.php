<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Grammar\Compiled\Model;

use PhpArchitecture\Parser\Foundation\Matching\Event\Contract\MatchingEvent;
use PhpArchitecture\Parser\Foundation\Matching\Event\Contract\MatchingEventListener;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\Contract\TokenizationEvent;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\Contract\TokenizationEventListener;

final readonly class CompiledEventSubscriber
{
    /** 
     * @param class-string<TokenizationEvent|MatchingEvent> $eventClassName
     */
    public function __construct(
        public string $eventClassName,
        public TokenizationEventListener|MatchingEventListener $listener,
        public ?string $onlyForRuleName,
        public int $priority,
    ) {}
}
