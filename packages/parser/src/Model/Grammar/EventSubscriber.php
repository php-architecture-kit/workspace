<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Model\Grammar;

use Closure;
use PhpArchitecture\Parser\Event\EventInterface;

final class EventSubscriber
{
    public function __construct(
        public readonly string $eventName,
        public readonly ?string $ruleName,
        public readonly ?Closure $callback,
        public readonly int $priority,
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
        return new self($eventName, $ruleName, $callback ? Closure::fromCallable($callback) : null, $priority);
    }
}
