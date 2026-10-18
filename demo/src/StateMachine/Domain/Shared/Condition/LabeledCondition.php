<?php

declare(strict_types=1);

namespace PhpArchitecture\Demo\StateMachine\Domain\Shared\Condition;

use PhpArchitecture\StateMachine\Foundation\Transition\Condition\TransitionCondition;

interface LabeledCondition extends TransitionCondition
{
    /** Caption of the transition in the graph. */
    public function label(): string;
}
