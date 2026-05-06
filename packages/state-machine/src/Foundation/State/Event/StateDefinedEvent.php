<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\State\Event;

use PhpArchitecture\DomainCore\DomainEvent;
use PhpArchitecture\StateMachine\Foundation\State\Identity\StateId;
use PhpArchitecture\StateMachine\Foundation\State\Property\StateDetail;

final readonly class StateDefinedEvent implements DomainEvent
{
    /** 
     * @param array<string,StateDetail> $details
     */
    public function __construct(
        public StateId $stateId,
        public array $details,
    ) {}
}
