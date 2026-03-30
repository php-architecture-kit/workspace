<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Definition\Middleware;

use PhpArchitecture\Parser\Grammar\Definition\EventSubscriber;

class AddEventSubscriberMiddleware extends AbstractMiddleware
{
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
        return self::ADD_EVENT_SUBSCRIBER;
    }
}
