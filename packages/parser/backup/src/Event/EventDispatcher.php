<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Event;

use InvalidArgumentException;

class EventDispatcher
{
    /** @var array<class-string<EventInterface>,bool> */
    private array $sortedEventSubscribers = [];
    /** @var array<class-string<EventInterface>,EventSubscriber[]> */
    private array $eventSubscribers = [];

    /**
     * @param array<class-string<EventInterface>> $allowedEvents
     */
    public function __construct(
        public readonly array $allowedEvents = []
    ) {
        if (empty($this->allowedEvents)) {
            throw new InvalidArgumentException("Allowed Events list cannot be empty.");
        }
    }

    public function dispatch(EventInterface $event): void
    {
        $eventName = $event::class;
        
        if (!in_array($eventName, $this->allowedEvents, true)) {
            throw new InvalidArgumentException("Event {$eventName} is not allowed to be dispatched");
        }
        
        if (($this->sortedEventSubscribers[$eventName] ?? false) === false) {
            $this->sortEventSubscribers($eventName);
        }

        foreach ($this->eventSubscribers[$eventName] ?? [] as $eventSubscriber) {
            $eventSubscriber->onEvent($event);
        }
    }

    public function registerEventSubscriber(EventSubscriber $eventSubscriber): void
    {
        $eventName = $eventSubscriber->eventName();
        if (!in_array($eventName, $this->allowedEvents)) {
            throw new \InvalidArgumentException("Event {$eventName} is not allowed");
        }

        $this->eventSubscribers[$eventName][] = $eventSubscriber;
        $this->sortedEventSubscribers[$eventName] = false;
    }

    private function sortEventSubscribers(string $eventName): void
    {
        usort(
            $this->eventSubscribers[$eventName],
            static fn(EventSubscriber $a, EventSubscriber $b): int => $a->priority() <=> $b->priority()
        );
        $this->sortedEventSubscribers[$eventName] = true;
    }
}
