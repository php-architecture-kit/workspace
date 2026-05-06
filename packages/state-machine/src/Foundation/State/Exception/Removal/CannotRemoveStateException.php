<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\State\Exception\Removal;

use RuntimeException;

final class CannotRemoveStateException extends RuntimeException implements StateRemovalException {}
