<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\State\Exception\Modification;

use RuntimeException;

final class CannotModifyStateException extends RuntimeException implements StateModificationException {}
