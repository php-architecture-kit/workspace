<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Config\Exception;

use LogicException;

final class NoTransitionStrategyException extends LogicException implements StateMachineConfigException {}
