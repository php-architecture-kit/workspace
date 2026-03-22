<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar;

use Closure;
// use PhpArchitecture\Parser\Grammar\EventListener\DelayedDispatchEventListener;
use PhpArchitecture\Parser\Grammar\EventListener\RuleMatchedEventListener;
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
        public readonly Closure|TokenizationEventListener|ParsingEventListener $listener,
        public ?string $onlyForRuleName = null,
        // public ?string $delayUntilEvent = null,
        public int $priority = 0,
    ) {}

    public static function on(string $eventClassName, TokenizationEventListener|ParsingEventListener|callable $listener): self
    {
        if (is_callable($listener)) {
            $listener = Closure::fromCallable($listener);
        }

        return new self(
            eventClassName: $eventClassName,
            listener: $listener,
            onlyForRuleName: $listener instanceof RuleMatchedEventListener ? $listener->rule() : null,
            // delayUntilEvent: $listener instanceof DelayedDispatchEventListener ? $listener->triggerEvent() : null,
            priority: $listener->priority(),
        );
    }

    public function onlyForRuleName(string $ruleName): self
    {
        $this->onlyForRuleName = $ruleName;
        return $this;
    }

    // /** @param class-string<TokenizationEvent|ParsingEvent> $eventClassName */
    // public function delayUntilEvent(string $eventClassName): self
    // {
    //     $this->delayUntilEvent = $eventClassName;
    //     return $this;
    // }

    public function priority(int $priority): self
    {
        $this->priority = $priority;
        return $this;
    }
}
