<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Pointer\Exception\Transition;

use RuntimeException;

final class CannotTransitionPointerException extends RuntimeException implements PointerTransitionException {}
