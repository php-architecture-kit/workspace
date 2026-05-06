<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Pointer\Strategy\Default;

use PhpArchitecture\StateMachine\Foundation\Pointer\Pointer;
use PhpArchitecture\StateMachine\Foundation\Pointer\Pointers;
use PhpArchitecture\StateMachine\Foundation\Pointer\Strategy\PointerExecutionPlan;
use PhpArchitecture\StateMachine\Foundation\Pointer\Strategy\PointersSelectionStrategy;

final class AllPointersUntilBlockedStrategy implements PointersSelectionStrategy
{
    public function select(Pointers $pointers): array
    {
        return array_values(array_map(
            static fn(Pointer $pointer): PointerExecutionPlan => PointerExecutionPlan::untilBlocked($pointer),
            $pointers->pointers,
        ));
    }
}
