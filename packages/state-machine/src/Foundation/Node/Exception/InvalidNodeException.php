<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Node\Exception;

use InvalidArgumentException;

final class InvalidNodeException extends InvalidArgumentException implements StateMachineNodeException {}
