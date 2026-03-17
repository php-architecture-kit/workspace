<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Event;

interface EventSubscriber
{
    /** @return class-string<EventInterface> */
    public function eventName(): string;

    public function priority(): int;
    
    public function onEvent(EventInterface $event): void;
}
