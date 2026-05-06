<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\State\Policy\State;

use PhpArchitecture\StateMachine\Foundation\State\State;
use PhpArchitecture\StateMachine\Foundation\State\States;
use PhpArchitecture\StateMachine\Foundation\State\Exception\Definition\StateDefinitionException;

interface StateDefinitionPolicy 
{
    /**
     * @throws StateDefinitionException
     */
    public function assertStateDefinitionAllowed(State $state, States $states): void;
}
