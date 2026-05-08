<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Tokenization\Context;

use PhpArchitecture\Parser\Foundation\Tokenization\Contract\TokenizationContext;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\Contract\RemovableEventListener;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\Contract\TokenBasedEvent;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\Contract\TokenizationEvent;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\Contract\TokenizationEventListener;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\Contract\TokenRegionBasedEvent;

class TokenizationEventDispatcher
{
    private const NS_SHARED = '__shared__';

    /** @var array<string,array<class-string<TokenizationEvent>,array<TokenizationEventListener>>> */
    private array $listeners = [];

    /** @var array{event:TokenizationEvent,listeners:TokenizationEventListener[]}[] */
    private array $handledEvents = [];

    public function __construct(
        private readonly TokenizationContext $context
    ) {
        $this->listeners[self::NS_SHARED] = [];
    }

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
        $this->handledEvents[] = ['event' => $event, 'listeners' => $listeners];

        foreach ($listeners as $listener) {
            $listener->handle($event, $this->context);

            if ($listener instanceof RemovableEventListener && $listener->shouldBeRemoved()) {
                $this->removeListener($listener);
            }
        }
    }

    /** @return TokenizationEventListener[] */
    private function eventListenersForEvent(TokenizationEvent $event): array
    {
        $eventClassName = $event::class;
        $namespace = match (true) {
            $event instanceof TokenBasedEvent => $event->name(),
            $event instanceof TokenRegionBasedEvent => $event->name(),
            default => self::NS_SHARED,
        };

        return $this->listeners[$namespace][$eventClassName] ?? $this->listeners[self::NS_SHARED][$eventClassName] ?? [];
    }

    private function removeListener(TokenizationEventListener $listener): void
    {
        foreach ($this->listeners as $namespace => $listenersMap) {
            foreach ($listenersMap as $eventClassName => $listeners) {
                $this->listeners[$namespace][$eventClassName] = array_values(
                    array_filter(
                        $listeners,
                        static fn(TokenizationEventListener $l) => $l !== $listener,
                    ),
                );
            }
        }
    }

    /**
     * @param TokenizationEventListener[] $listeners
     */
    private function sortListenersByPriority(array &$listeners): void
    {
        usort($listeners, static fn($a, $b) => $a->priority() <=> $b->priority());
    }
}
