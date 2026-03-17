<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tokenization;

use PhpArchitecture\Parser\Tokenization\Event\Contract\TokenBasedEvent;
use PhpArchitecture\Parser\Tokenization\Event\Contract\TokenizationEvent;
use PhpArchitecture\Parser\Tokenization\Event\Contract\TokenizationEventListener;

class TokenizationEventDispatcher
{
    private const NS_SHARED = '__shared__';

    /** @var array<string,array<class-string<TokenizationEvent>,array<TokenizationEventListener>>> */
    private array $listeners = [];

    public function __construct(
        private readonly Tokenization $context
    ) {}

    /** @param class-string<TokenizationEvent> $eventClassName */
    public function registerEventListener(
        TokenizationEventListener $listener,
        string $eventClassName,
        ?string $onlyOnRule = null
    ): void {
        $namespace = $onlyOnRule ?? self::NS_SHARED;

        if ($namespace === self::NS_SHARED) {
            foreach ($this->listeners as $ns => $listenersMap) {
                $this->listeners[$ns][$eventClassName][] = $listener;
                $this->sortListenersByPriority($this->listeners[$ns][$eventClassName]);
            }

            return;
        }

        if (!array_key_exists($namespace, $this->listeners)) {
            $this->listeners[$namespace] = $this->listeners[self::NS_SHARED] ?? [];
        }

        $this->listeners[$namespace][$eventClassName][] = $listener;
        $this->sortListenersByPriority($this->listeners[$namespace][$eventClassName]);
    }

    public function dispatchEvent(TokenizationEvent $event): void
    {
        /** @var TokenizationEventListener[] $listeners */
        $listeners = $this->eventListenersForEvent($event);

        foreach ($listeners as $listener) {
            $listener->handle($event, $this->context);
        }
    }

    /** @return TokenizationEventListener[] */
    private function eventListenersForEvent(TokenizationEvent $event): array
    {
        $eventClassName = $event::class;
        $namespace = $event instanceof TokenBasedEvent ? $event->name() : self::NS_SHARED;

        return $this->listeners[$namespace][$eventClassName] ?? [];
    }

    /** 
     * @param TokenizationEventListener[]
     */
    private function sortListenersByPriority(array &$listeners): void
    {
        usort($listeners, static fn($a, $b) => $a->priority() <=> $b->priority());
    }
}
