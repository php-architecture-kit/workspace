<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\State\Exception\Definition;

use PhpArchitecture\DomainCore\Exception\InvalidStateCausedException;

class InvalidStateCausedByStateDefinition extends InvalidStateCausedException implements StateDefinitionException
{
    
}
