<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Compiled\Model;

use PhpArchitecture\Parser\Matching\Event\Contract\ParsingEvent;
use PhpArchitecture\Parser\Matching\Event\Contract\ParsingEventListener;
use PhpArchitecture\Parser\Processing\Event\Tokenization\Contract\TokenizationEvent;
use PhpArchitecture\Parser\Processing\Event\Tokenization\Contract\TokenizationEventListener;

final readonly class CompiledEventSubscriber
{
    /** 
     * @param class-string<TokenizationEvent|ParsingEvent> $eventClassName
     */
    public function __construct(
        public string $eventClassName,
        public TokenizationEventListener|ParsingEventListener $listener,
        public ?string $onlyForRuleName,
        public int $priority,
    ) {}
}
