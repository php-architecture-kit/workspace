<?php

declare(strict_types=1);

namespace PhpArchitecture\Demo\StateMachine\Domain\Shared\Step;

use Closure;
use PhpArchitecture\StateMachine\Foundation\State\States;
use PhpArchitecture\StateMachine\Foundation\Node\Node;

/**
 * A step of a demo workflow: what happens when a pointer enters the node.
 *
 * The effect runs once per visit (StepNodeHandler), the same way the package's own
 * CreateAsyncTaskNode carries its task factory.
 */
final class StepNode extends Node
{
    /**
     * @param string                $label  short caption shown in the graph
     * @param Closure(States): void  $effect
     */
    public function __construct(
        string $name,
        public readonly string $label,
        public readonly Closure $effect,
    ) {
        parent::__construct($name);
    }

    public function handlerClass(): string
    {
        return StepNodeHandler::class;
    }
}
