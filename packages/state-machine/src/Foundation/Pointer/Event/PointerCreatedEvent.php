<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Pointer\Event;

use PhpArchitecture\DomainCore\DomainEvent;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\Pointer\Identity\PointerId;

final readonly class PointerCreatedEvent implements DomainEvent
{
    public function __construct(
        public PointerId $pointerId,
        public ?PointerId $parentId,
        public NodeId $nodeId,
    ) {}
}
