<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Pointer\Policy;

use PhpArchitecture\StateMachine\Foundation\Pointer\Exception\Transition\PointerTransitionException;
use PhpArchitecture\StateMachine\Foundation\Pointer\Pointer;
use PhpArchitecture\StateMachine\Foundation\Pointer\Pointers;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;

interface PointerTransitionPolicy
{
    /**
     * @throws PointerTransitionException
     */
    public function assertPointerTransitionAllowed(Pointer $pointer, Pointers $pointers, NodeId ...$to): void;
}
