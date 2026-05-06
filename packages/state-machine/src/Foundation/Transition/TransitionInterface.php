<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Transition;

use PhpArchitecture\Graph\Edge\EdgeInterface;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\Transition\Identity\TransitionId;

interface TransitionInterface extends EdgeInterface 
{
    public function id(): TransitionId;

    public function u(): NodeId;

    public function v(): NodeId;

    /** @return string[] */
    public function tags(): array;
}
