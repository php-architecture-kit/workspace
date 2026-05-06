<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\State\Policy\State;

use PhpArchitecture\StateMachine\Foundation\State\Exception\Modification\StateModificationException;
use PhpArchitecture\StateMachine\Foundation\State\State;
use PhpArchitecture\StateMachine\Foundation\State\States;

interface StateModificationPolicy 
{
    /**
     * @throws StateModificationException
     */
    public function assertStateModificationAllowed(State $original, State $changed, States $states): void;
}
