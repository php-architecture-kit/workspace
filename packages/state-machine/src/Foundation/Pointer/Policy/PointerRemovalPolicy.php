<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Pointer\Policy;

use PhpArchitecture\StateMachine\Foundation\Pointer\Exception\Removal\PointerRemovalException;
use PhpArchitecture\StateMachine\Foundation\Pointer\Pointer;
use PhpArchitecture\StateMachine\Foundation\Pointer\Pointers;

interface PointerRemovalPolicy 
{
    /**
     * @throws PointerRemovalException
     */
    public function assertPointerRemovalAllowed(Pointer $pointer, Pointers $pointers): void;
}
