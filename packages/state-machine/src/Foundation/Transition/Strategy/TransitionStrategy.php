<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Transition\Strategy;

use PhpArchitecture\StateMachine\Foundation\Execution\Execution;
use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\Output\TransitionSelectionOutput;
use PhpArchitecture\StateMachine\Foundation\Pointer\Pointer;

interface TransitionStrategy
{
    public function supports(TransitionSelectionOutput $transitionSelection): bool;

    public function transitionToNextNodes(
        Execution $execution,
        Pointer $pointer,
        TransitionSelectionOutput $transitionSelection
    ): void;
}
