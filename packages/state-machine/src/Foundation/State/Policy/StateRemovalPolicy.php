<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\State\Policy\State;

use PhpArchitecture\StateMachine\Foundation\State\Exception\Removal\StateRemovalException;
use PhpArchitecture\StateMachine\Foundation\State\State;
use PhpArchitecture\StateMachine\Foundation\State\States;

interface StateRemovalPolicy
{
    /**
     * @throws StateRemovalException
     */
    public function assertStateRemovalAllowed(State $state, States $states): void;
}
