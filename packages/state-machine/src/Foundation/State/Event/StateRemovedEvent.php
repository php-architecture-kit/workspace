<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\State\Event;

use PhpArchitecture\DomainCore\DomainEvent;
use PhpArchitecture\StateMachine\Foundation\State\Identity\StateId;

final readonly class StateRemovedEvent implements DomainEvent
{
    public function __construct(
        public StateId $stateId,
    ) {}
}
