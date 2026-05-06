<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\State;

use PhpArchitecture\DomainCore\AggregateRoot;
use PhpArchitecture\StateMachine\Foundation\Execution\Identity\ExecutionId;
use PhpArchitecture\StateMachine\Foundation\State\Event\StateDefinedEvent;
use PhpArchitecture\StateMachine\Foundation\State\Event\StateModifiedEvent;
use PhpArchitecture\StateMachine\Foundation\State\Event\StateRemovedEvent;
use PhpArchitecture\StateMachine\Foundation\State\Exception\Definition\StateDefinitionException;
use PhpArchitecture\StateMachine\Foundation\State\Exception\Modification\CannotModifyStateException;
use PhpArchitecture\StateMachine\Foundation\State\Exception\Modification\StateModificationException;
use PhpArchitecture\StateMachine\Foundation\State\Exception\Removal\CannotRemoveStateException;
use PhpArchitecture\StateMachine\Foundation\State\Exception\Removal\StateRemovalException;
use PhpArchitecture\StateMachine\Foundation\State\Identity\StateId;
use PhpArchitecture\StateMachine\Foundation\State\Policy\State\StateDefinitionPolicy;
use PhpArchitecture\StateMachine\Foundation\State\Policy\State\StateModificationPolicy;
use PhpArchitecture\StateMachine\Foundation\State\Policy\State\StateRemovalPolicy;
use PhpArchitecture\StateMachine\Foundation\State\Property\StateDetail;
use PhpArchitecture\Technical\ArrayTransformation;
use PhpArchitecture\Technical\Assert;

class States extends AggregateRoot
{
    /** 
     * @param State[] $states
     */
    protected function __construct(
        public readonly ExecutionId $executionId,
        public readonly ?StateDefinitionPolicy $definitionPolicy,
        public readonly ?StateModificationPolicy $modificationPolicy,
        public readonly ?StateRemovalPolicy $removalPolicy,
        public protected(set) array $states,
    ) {
        Assert::eachInstanceOf($states, State::class);
        $this->states = ArrayTransformation::indexBy($states, static fn(State $state) => $state->id->toString());
    }

    public static function create(
        ExecutionId $executionId,
        ?StateDefinitionPolicy $definitionPolicy,
        ?StateModificationPolicy $modificationPolicy,
        ?StateRemovalPolicy $removalPolicy,
    ): static {
        return new static($executionId, $definitionPolicy, $modificationPolicy, $removalPolicy, []);
    }

    public static function recreate(
        ExecutionId $executionId,
        ?StateDefinitionPolicy $definitionPolicy,
        ?StateModificationPolicy $modificationPolicy,
        ?StateRemovalPolicy $removalPolicy,
        array $states,
    ): static {
        return new static($executionId, $definitionPolicy, $modificationPolicy, $removalPolicy, $states);
    }

    /**
     * @param StateDetail[] $details
     * @throws StateDefinitionException
     */
    public function defineState(string $name, array $details): State
    {
        $state = State::create($this->executionId, $name, $details);

        if ($this->definitionPolicy !== null) {
            $this->definitionPolicy->assertStateDefinitionAllowed($state, $this);
        }

        $this->states[$state->id->toString()] = $state;
        $this->recordEvent(new StateDefinedEvent($state->id, $state->details));

        return $state;
    }

    /**
     * @param StateDetail[] $detailsToAdd
     * @param string[] $detailsToRemove
     * @throws StateModificationException
     */
    public function modifyState(StateId $stateId, array $detailsToAdd, array $detailsToRemove): void
    {
        $state = $this->states[$stateId->toString()] ?? null;
        if ($state === null) {
            throw new CannotModifyStateException("Requested State to perform modify operation does not exist.");
        }

        Assert::eachString($detailsToRemove, CannotModifyStateException::class);
        Assert::eachInstanceOf($detailsToAdd, StateDetail::class, CannotModifyStateException::class);

        $details = $state->details;
        $addedDetails = [];
        $removedDetails = [];
        foreach ($detailsToRemove as $detailToRemove) {
            $detail = $details[$detailToRemove] ?? null;
            if (null === $detail) {
                throw new CannotModifyStateException("Detail to remove does not exist in State named `{$state->name}` with id: `{$stateId->toString()}`.");
            }

            $removedDetails[$detailToRemove] = $detail;
            unset($details[$detailToRemove]);
        }

        foreach ($detailsToAdd as $detailToAdd) {
            $existingDetail = $details[$detailToAdd->name] ?? null;
            if (null !== $existingDetail) {
                $removedDetails[$detailToAdd->name] = $existingDetail;
                unset($details[$detailToAdd->name]);
            }

            $addedDetails[$detailToAdd->name] = $detailToAdd;
            $details[$detailToAdd->name] = $detailToAdd;
        }

        $changedState = State::recreate(
            $state->executionId,
            $state->id,
            $state->name,
            $details,
        );

        if ($this->modificationPolicy !== null) {
            $this->modificationPolicy->assertStateModificationAllowed($state, $changedState, $this);
        }

        $this->states[$stateId->toString()] = $changedState;
        $this->recordEvent(new StateModifiedEvent($state->id, $removedDetails, $addedDetails));
    }

    /**
     * @throws StateRemovalException
     */
    public function removeState(StateId $stateId): void
    {
        $state = $this->states[$stateId->toString()] ?? null;
        if ($state === null) {
            throw new CannotRemoveStateException("Requested State to perform remove operation does not exist.");
        }

        if ($this->removalPolicy !== null) {
            $this->removalPolicy->assertStateRemovalAllowed($state, $this);
        }

        unset($this->states[$stateId->toString()]);
        $this->recordEvent(new StateRemovedEvent($stateId));
    }
}
