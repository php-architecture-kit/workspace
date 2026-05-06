<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\State\Exception\Definition;

use PhpArchitecture\DomainCore\Exception\InvalidInputException;

class InvalidInputProvidedToStateDefinition extends InvalidInputException implements StateDefinitionException
{
    
}
