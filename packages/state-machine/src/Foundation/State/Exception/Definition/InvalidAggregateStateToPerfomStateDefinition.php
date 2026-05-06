<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\State\Exception\Definition;

use PhpArchitecture\DomainCore\Exception\InvalidStateToPerformActionException;

class InvalidAggregateStateToPerfomStateDefinition extends InvalidStateToPerformActionException implements StateDefinitionException
{
    
}
