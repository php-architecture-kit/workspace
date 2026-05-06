<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Node\Handler;

interface NodeHandlerInterface
{
    public function handle(NodeHandlerContext $context): NodeHandlerResult;
}
