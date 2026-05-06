<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Node\Exception;

use RuntimeException;

final class NodeNotFoundException extends RuntimeException implements StateMachineNodeException {}
