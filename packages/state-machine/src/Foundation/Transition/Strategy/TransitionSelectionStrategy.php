<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Transition\Strategy;

use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\Output\TransitionSelectionOutput;
use PhpArchitecture\StateMachine\Foundation\Pointer\Pointer;
use PhpArchitecture\StateMachine\Foundation\State\States;
use PhpArchitecture\StateMachine\Foundation\Transition\Transition;

interface TransitionSelectionStrategy
{
    /** 
     * @param Transition[] $transitions
     */
    public function select(
        Pointer $pointer,
        States $states,
        array $transitions,
    ): TransitionSelectionOutput;
}
