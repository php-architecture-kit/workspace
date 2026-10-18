<?php

declare(strict_types=1);

namespace PhpArchitecture\Demo\StateMachine\Domain\Shared\Condition;

use Closure;
use PhpArchitecture\Demo\StateMachine\Domain\Shared\World;
use PhpArchitecture\StateMachine\Foundation\State\States;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\Output\TransitionConditionDecision;

/**
 * One branch of a decision: all branches leaving a node share the same decide function,
 * so exactly one of them is Accepted (the others Rejected), or all Wait while it returns null.
 *
 * Mixing Wait and Accepted on one node would make the engine fork the pointer
 * (WaitAndForkStrategy) - a shared decision keeps the branches exclusive.
 */
final readonly class Route implements LabeledCondition
{
    /** @param Closure(World): ?string $decide */
    public function __construct(
        private Closure $decide,
        public string $branch,
        private string $label,
    ) {
    }

    public function check(States $states): TransitionConditionDecision
    {
        $decision = ($this->decide)(new World($states));

        return match ($decision) {
            null => TransitionConditionDecision::Wait,
            $this->branch => TransitionConditionDecision::Accepted,
            default => TransitionConditionDecision::Rejected,
        };
    }

    public function label(): string
    {
        return $this->label;
    }
}
