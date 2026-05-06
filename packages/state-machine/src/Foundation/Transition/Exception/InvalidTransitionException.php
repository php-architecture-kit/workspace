<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Transition\Exception;

use InvalidArgumentException;

final class InvalidTransitionException extends InvalidArgumentException implements StateMachineTransitionException {}
