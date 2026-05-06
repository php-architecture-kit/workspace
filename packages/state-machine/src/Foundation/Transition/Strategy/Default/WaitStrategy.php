<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Transition\Strategy\Default;

use PhpArchitecture\StateMachine\Foundation\Execution\Execution;
use PhpArchitecture\StateMachine\Foundation\Pointer\Pointer;
use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\Output\TransitionSelectionOutput;
use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\TransitionStrategy;

final class WaitStrategy implements TransitionStrategy
{
    public function supports(TransitionSelectionOutput $transitionSelection): bool
    {
        return !empty($transitionSelection->waitfor) && empty($transitionSelection->goto);
    }

    public function transitionToNextNodes(
        Execution $execution,
        Pointer $pointer,
        TransitionSelectionOutput $transitionSelection,
    ): void {
    }
}
