<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Middleware;

use Closure;

final class AddEventSubscriberMiddleware implements GrammarMiddleware
{
    public function __construct(
        private Closure $callback,
        private int $priority = 0,
    ) {}

    /**
     * @param EventSubscriber $EventSubscriber
     * @return EventSubscriber
     */
    public function handle(object $EventSubscriber): object
    {
        return ($this->callback)($EventSubscriber);
    }

    public function method(): string
    {
        return self::ADD_EVENT_HANDLER;
    }

    public function priority(): int
    {
        return $this->priority;
    }
}
