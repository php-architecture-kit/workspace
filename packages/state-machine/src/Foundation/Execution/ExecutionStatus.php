<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Execution;

enum ExecutionStatus: string
{
    case Completed = 'completed';
    case Suspended = 'suspended';
    case Running = 'running';
}
