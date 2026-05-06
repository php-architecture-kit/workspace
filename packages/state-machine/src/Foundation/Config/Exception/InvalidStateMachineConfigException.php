<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Config\Exception;

use InvalidArgumentException;

final class InvalidStateMachineConfigException extends InvalidArgumentException implements StateMachineConfigException {}
