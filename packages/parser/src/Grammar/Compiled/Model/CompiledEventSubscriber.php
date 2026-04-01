<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Compiled\Model;

use PhpArchitecture\Parser\Processing\Event\Matching\Contract\MatchingEvent;
use PhpArchitecture\Parser\Processing\Event\Matching\Contract\MatchingEventListener;
use PhpArchitecture\Parser\Processing\Event\Tokenization\Contract\TokenizationEvent;
use PhpArchitecture\Parser\Processing\Event\Tokenization\Contract\TokenizationEventListener;

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
