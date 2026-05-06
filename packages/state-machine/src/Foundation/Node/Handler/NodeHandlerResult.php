<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Node\Handler;

enum NodeHandlerResult: string
{
    case Continue = 'continue';
    case Suspended = 'suspended';
}
