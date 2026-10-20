<?php

declare(strict_types=1);

namespace PhpArchitecture\Demo\StateMachine\Domain\Shared\Step;

use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerContext;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerInterface;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerResult;

final class StepNodeHandler implements NodeHandlerInterface
{
    public function handle(NodeHandlerContext $context): NodeHandlerResult
    {
        $node = $context->node;
        assert($node instanceof StepNode);

        ($node->effect)($context->states);

        return NodeHandlerResult::Continue;
    }
}
