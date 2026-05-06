<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Execution;

use PhpArchitecture\StateMachine\Foundation\Execution\Identity\ExecutionId;
use PhpArchitecture\StateMachine\Foundation\Pointer\Pointers;
use PhpArchitecture\StateMachine\Foundation\Pointer\Policy\PointerCreationPolicy;
use PhpArchitecture\StateMachine\Foundation\Pointer\Policy\PointerRemovalPolicy;
use PhpArchitecture\StateMachine\Foundation\Pointer\Policy\PointerTransitionPolicy;
use PhpArchitecture\StateMachine\Foundation\State\Policy\State\StateDefinitionPolicy;
use PhpArchitecture\StateMachine\Foundation\State\Policy\State\StateModificationPolicy;
use PhpArchitecture\StateMachine\Foundation\State\Policy\State\StateRemovalPolicy;
use PhpArchitecture\StateMachine\Foundation\State\States;

class Execution
{
    protected function __construct(
        public readonly ExecutionId $id,
        public readonly Pointers $pointers,
        public readonly States $states,
    ) {}

    public static function create(
        ?PointerCreationPolicy $pointerCreationPolicy = null,
        ?PointerTransitionPolicy $pointerTransitionPolicy = null,
        ?PointerRemovalPolicy $pointerRemovalPolicy = null,
        ?StateDefinitionPolicy $stateDefinitionPolicy = null,
        ?StateModificationPolicy $stateModificationPolicy = null,
        ?StateRemovalPolicy $stateRemovalPolicy = null,
    ): static {
        $id = ExecutionId::new();

        return new static(
            $id,
            Pointers::create($id, $pointerCreationPolicy, $pointerTransitionPolicy, $pointerRemovalPolicy),
            States::create($id, $stateDefinitionPolicy, $stateModificationPolicy, $stateRemovalPolicy),
        );
    }

    public static function recreate(
        ExecutionId $id,
        Pointers $pointers,
        States $states,
    ): static {
        return new static(
            $id,
            $pointers,
            $states,
        );
    }
}
