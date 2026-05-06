<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Pointer\Policy;

use PhpArchitecture\StateMachine\Foundation\Pointer\Exception\Creation\PointerCreationException;
use PhpArchitecture\StateMachine\Foundation\Pointer\Pointer;
use PhpArchitecture\StateMachine\Foundation\Pointer\Pointers;

interface PointerCreationPolicy 
{
    /**
     * @throws PointerCreationException
     */
    public function assertPointerCreationAllowed(Pointer $pointer, Pointers $pointers): void;
}
