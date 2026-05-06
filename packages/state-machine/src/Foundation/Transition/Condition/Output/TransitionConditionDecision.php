<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Transition\Condition\Output;

enum TransitionConditionDecision: string
{
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Wait = 'wait';
}
