<?php

declare(strict_types=1);

namespace PhpArchitecture\Demo\StateMachine\Domain\Shared\Condition;

use Closure;
use PhpArchitecture\StateMachine\Foundation\State\States;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\Output\TransitionConditionDecision;

/** The pointer waits until the predicate holds - the only way out of its node. */
final readonly class When implements LabeledCondition
{
    /** @param Closure(States): bool $predicate */
    public function __construct(
        private Closure $predicate,
        private string $label,
    ) {
    }

    public function check(States $states): TransitionConditionDecision
    {
        return ($this->predicate)($states)
            ? TransitionConditionDecision::Accepted
            : TransitionConditionDecision::Wait;
    }

    public function label(): string
    {
        return $this->label;
    }
}
