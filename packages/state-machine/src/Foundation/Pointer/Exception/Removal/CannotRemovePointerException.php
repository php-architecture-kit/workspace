<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Pointer\Exception\Removal;

use RuntimeException;

final class CannotRemovePointerException extends RuntimeException implements PointerRemovalException {}
