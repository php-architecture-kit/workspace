<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Node\Handler;

use PhpArchitecture\StateMachine\Foundation\Execution\Identity\ExecutionId;
use PhpArchitecture\StateMachine\Foundation\Node\Exception\InvalidNodeException;
use PhpArchitecture\StateMachine\Foundation\Node\NodeInterface;
use PhpArchitecture\StateMachine\Foundation\Pointer\Pointer;
use PhpArchitecture\StateMachine\Foundation\State\States;

readonly class NodeHandlerContext
{
    public function __construct(
        public ExecutionId $executionId,
        public NodeInterface $node,
        public Pointer $pointer,
        public States $states,
    ) {
        if (!$pointer->nodeId->equals($node->id())) {
            throw new InvalidNodeException(
                "Pointer node ID '{$pointer->nodeId}' does not match handler context node ID '{$node->id()}'."
            );
        }
    }
}
