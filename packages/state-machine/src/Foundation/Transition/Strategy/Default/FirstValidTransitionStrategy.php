<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Transition\Strategy\Default;

use PhpArchitecture\StateMachine\Foundation\Pointer\Pointer;
use PhpArchitecture\StateMachine\Foundation\State\States;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\Output\TransitionConditionDecision;
use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\Output\TransitionSelectionOutput;
use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\TransitionSelectionStrategy;

final class FirstValidTransitionStrategy implements TransitionSelectionStrategy
{
    public function select(Pointer $pointer, States $states, array $transitions): TransitionSelectionOutput
    {
        $waitfor = [];
        $reject = [];

        foreach ($transitions as $transition) {
            if ($transition->condition === null) {
                return new TransitionSelectionOutput([$transition], $waitfor, $reject);
            }

            $decision = $transition->condition->check($states);

            if ($decision === TransitionConditionDecision::Accepted) {
                return new TransitionSelectionOutput([$transition], $waitfor, $reject);
            }

            match ($decision) {
                TransitionConditionDecision::Wait     => $waitfor[] = $transition,
                TransitionConditionDecision::Rejected => $reject[] = $transition,
                default                               => null,
            };
        }

        return new TransitionSelectionOutput([], $waitfor, $reject);
    }
}
