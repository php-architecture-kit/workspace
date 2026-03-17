<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Model\Grammar\EventSubscriber;

use Closure;
use PhpArchitecture\Parser\Event\EventInterface;
use PhpArchitecture\Parser\Event\EventSubscriber as EventSubscriberInterface;

final class EventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        public readonly string $eventName,
        public readonly ?Closure $callback,
        public readonly int $priority,
        public readonly ?string $ruleName,
    ) {}

    /**
     * @param class-string<EventInterface> $eventName
     * @param callable(EventInterface):void $callback
     */
    public static function new(
        string $eventName,
        ?string $ruleName = null,
        ?callable $callback = null,
        int $priority = 0
    ): self {
        return new self($eventName, $callback ? Closure::fromCallable($callback) : null, $priority, $ruleName);
    }

    public function eventName(): string
    {
        return $this->eventName;
    }

    public function priority(): int
    {
        return $this->priority;
    }

    public function onEvent(EventInterface $event): void
    {
        $this->callback->call($this, $event);
    }
}
