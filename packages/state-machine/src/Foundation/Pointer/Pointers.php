<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Pointer;

use PhpArchitecture\DomainCore\AggregateRoot;
use PhpArchitecture\StateMachine\Foundation\Pointer\Event\PointerCreatedEvent;
use PhpArchitecture\StateMachine\Foundation\Pointer\Event\PointerForkedEvent;
use PhpArchitecture\StateMachine\Foundation\Pointer\Event\PointerRemovedEvent;
use PhpArchitecture\StateMachine\Foundation\Pointer\Event\PointerTransitionedEvent;
use PhpArchitecture\StateMachine\Foundation\Pointer\Exception\Removal\CannotRemovePointerException;
use PhpArchitecture\StateMachine\Foundation\Pointer\Exception\Transition\CannotTransitionPointerException;
use PhpArchitecture\Technical\Assert;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\Execution\Identity\ExecutionId;
use PhpArchitecture\StateMachine\Foundation\Pointer\Identity\PointerId;
use PhpArchitecture\StateMachine\Foundation\Pointer\Policy\PointerCreationPolicy;
use PhpArchitecture\StateMachine\Foundation\Pointer\Policy\PointerRemovalPolicy;
use PhpArchitecture\StateMachine\Foundation\Pointer\Policy\PointerTransitionPolicy;
use PhpArchitecture\Technical\ArrayTransformation;

class Pointers extends AggregateRoot
{
    /**
     * @param Pointer[] $pointers
     */
    protected function __construct(
        public readonly ExecutionId $executionId,
        public readonly ?PointerCreationPolicy $creationPolicy,
        public readonly ?PointerTransitionPolicy $transitionPolicy,
        public readonly ?PointerRemovalPolicy $removalPolicy,
        public protected(set) array $pointers,
    ) {
        Assert::eachInstanceOf($pointers, Pointer::class);
        $this->pointers = ArrayTransformation::indexBy($pointers, static fn(Pointer $pointer) => $pointer->id->__toString());
    }

    public static function create(
        ExecutionId $executionId,
        ?PointerCreationPolicy $creationPolicy,
        ?PointerTransitionPolicy $transitionPolicy,
        ?PointerRemovalPolicy $removalPolicy,
    ): static {
        return new static(
            $executionId,
            $creationPolicy,
            $transitionPolicy,
            $removalPolicy,
            [],
        );
    }

    public static function recreate(
        ExecutionId $executionId,
        ?PointerCreationPolicy $creationPolicy,
        ?PointerTransitionPolicy $transitionPolicy,
        ?PointerRemovalPolicy $removalPolicy,
        array $pointers,
    ): static {
        return new static(
            $executionId,
            $creationPolicy,
            $transitionPolicy,
            $removalPolicy,
            $pointers,
        );
    }

    public function startAt(NodeId $nodeId): Pointer
    {
        $pointer = Pointer::create($this->executionId, $nodeId);

        $this->creationPolicy?->assertPointerCreationAllowed($pointer, $this);
        $this->pointers[$pointer->id->toString()] = $pointer;
        $this->recordEvent(new PointerCreatedEvent($pointer->id, null, $nodeId));

        return $pointer;
    }

    public function remove(PointerId $pointerId): void
    {
        $pointer = $this->pointers[$pointerId->toString()] ?? null;
        if (null === $pointer) {
            throw new CannotRemovePointerException("Requested Pointer to remove does not exists in pointers collection.");
        }

        $this->removalPolicy?->assertPointerRemovalAllowed($pointer, $this);
        unset($this->pointers[$pointerId->toString()]);
        $this->recordEvent(new PointerRemovedEvent($pointerId, $pointer->nodeId, $pointer->currentStep));
    }

    public function forkTo(PointerId $pointerId, NodeId ...$to): void
    {
        $pointer = $this->pointers[$pointerId->toString()] ?? null;
        if (null === $pointer) {
            throw new CannotTransitionPointerException("Requested Pointer to fork does not exists in pointers collection.");
        }

        if (count($to) === 0) {
            throw new CannotTransitionPointerException("At least one target node must be provided for fork.");
        }

        $lastNodeId = $pointer->nodeId;

        foreach ($to as $nodeId) {
            $forkedPointer = $pointer->fork();
            $this->pointers[$forkedPointer->id->toString()] = $forkedPointer;
            $this->recordEvent(new PointerForkedEvent($pointerId, $forkedPointer->id, $nodeId));

            $forkedPointer->step($nodeId);
            $this->recordEvent(new PointerTransitionedEvent($forkedPointer->id, $lastNodeId, $nodeId, $forkedPointer->currentStep));
        }
    }

    public function transition(PointerId $pointerId, NodeId ...$to): void
    {
        $pointer = $this->pointers[$pointerId->toString()] ?? null;
        if (null === $pointer) {
            throw new CannotTransitionPointerException("Requested Pointer to perform transition does not exists in pointers collection.");
        }

        if (count($to) === 0) {
            throw new CannotTransitionPointerException("At least one target node must be provided for transition.");
        }

        $this->transitionPolicy?->assertPointerTransitionAllowed($pointer, $this, ...$to);
        $lastNodeId = $pointer->nodeId;

        if (count($to) === 1) {
            $nodeId = $to[0];

            $pointer->step($nodeId);
            $this->recordEvent(new PointerTransitionedEvent($pointerId, $lastNodeId, $nodeId, $pointer->currentStep));

            return;
        }

        foreach ($to as $nodeId) {
            $forkedPointer = $pointer->fork();
            $this->pointers[$forkedPointer->id->toString()] = $forkedPointer;
            $this->recordEvent(new PointerForkedEvent($pointerId, $forkedPointer->id, $nodeId));

            $forkedPointer->step($nodeId);
            $this->recordEvent(new PointerTransitionedEvent($forkedPointer->id, $lastNodeId, $nodeId, $forkedPointer->currentStep));
        }

        $this->remove($pointerId);
    }
}
