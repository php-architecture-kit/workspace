<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Transition\Strategy\Default;

use PhpArchitecture\StateMachine\Foundation\Pointer\Pointer;
use PhpArchitecture\StateMachine\Foundation\State\States;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\Output\TransitionConditionDecision;
use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\Output\TransitionSelectionOutput;
use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\TransitionSelectionStrategy;

final class AllValidTransitionsStrategy implements TransitionSelectionStrategy
{
    public function select(Pointer $pointer, States $states, array $transitions): TransitionSelectionOutput
    {
        $goto = [];
        $waitfor = [];
        $reject = [];

        foreach ($transitions as $transition) {
            if ($transition->condition === null) {
                $goto[] = $transition;
                continue;
            }

            $decision = $transition->condition->check($states);

            match ($decision) {
                TransitionConditionDecision::Accepted => $goto[] = $transition,
                TransitionConditionDecision::Wait     => $waitfor[] = $transition,
                TransitionConditionDecision::Rejected => $reject[] = $transition,
            };
        }

        return new TransitionSelectionOutput($goto, $waitfor, $reject);
    }
}
