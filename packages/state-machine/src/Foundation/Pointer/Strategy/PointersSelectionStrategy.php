<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Pointer\Strategy;

use PhpArchitecture\StateMachine\Foundation\Pointer\Pointers;

interface PointersSelectionStrategy
{
    /** @return PointerExecutionPlan[] */
    public function select(Pointers $pointers): array;
}
