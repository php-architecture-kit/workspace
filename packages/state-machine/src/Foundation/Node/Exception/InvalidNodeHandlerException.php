<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Node\Exception;

use LogicException;

final class InvalidNodeHandlerException extends LogicException implements StateMachineNodeException {}
