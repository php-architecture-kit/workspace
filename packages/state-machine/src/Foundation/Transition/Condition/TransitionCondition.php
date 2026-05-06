<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Transition\Condition;

use PhpArchitecture\StateMachine\Foundation\State\States;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\Output\TransitionConditionDecision;

interface TransitionCondition
{
    public function check(States $states): TransitionConditionDecision;
}
