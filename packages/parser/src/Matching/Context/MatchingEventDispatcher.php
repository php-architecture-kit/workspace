<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Matching\Context;

use PhpArchitecture\Parser\Processing\Context\MatchingContext;
use PhpArchitecture\Parser\Processing\Event\Matching\Contract\MatchingEvent;
use PhpArchitecture\Parser\Processing\Event\Matching\Contract\MatchingEventListener;
use PhpArchitecture\Parser\Processing\Event\Matching\Contract\RemovableEventListener;
use PhpArchitecture\Parser\Processing\Event\Matching\Contract\SequenceBasedEvent;

class MatchingEventDispatcher
{
    private const NS_SHARED = '__shared__';

    /** @var array<string,array<class-string<MatchingEvent>,array<MatchingEventListener>>> */
    private array $listeners = [];

    public function __construct(
        private readonly MatchingContext $context
    ) {
        $this->listeners[self::NS_SHARED] = [];
    }

    /** @param class-string<MatchingEvent> $eventClassName */
    public function registerEventListener(
        MatchingEventListener $listener,
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

    public function dispatchEvent(MatchingEvent $event): void
    {
        /** @var MatchingEventListener[] $listeners */
        $listeners = $this->eventListenersForEvent($event);

        foreach ($listeners as $listener) {
            $listener->handle($event, $this->context);

            if ($listener instanceof RemovableEventListener && $listener->shouldBeRemoved()) {
                $this->removeListener($listener);
            }
        }
    }

    /** @return MatchingEventListener[] */
    private function eventListenersForEvent(MatchingEvent $event): array
    {
        $eventClassName = $event::class;
        $namespace = match (true) {
            $event instanceof SequenceBasedEvent => $event->sequenceName(),
            default => self::NS_SHARED,
        };

        return $this->listeners[$namespace][$eventClassName] ?? $this->listeners[self::NS_SHARED][$eventClassName] ?? [];
    }

    private function removeListener(MatchingEventListener $listener): void
    {
        foreach ($this->listeners as $namespace => $listenersMap) {
            foreach ($listenersMap as $eventClassName => $listeners) {
                $this->listeners[$namespace][$eventClassName] = array_values(
                    array_filter(
                        $listeners,
                        static fn(MatchingEventListener $l) => $l !== $listener,
                    ),
                );
            }
        }
    }

    /**
     * @param MatchingEventListener[] $listeners
     */
    private function sortListenersByPriority(array &$listeners): void
    {
        usort($listeners, static fn($a, $b) => $a->priority() <=> $b->priority());
    }
}
