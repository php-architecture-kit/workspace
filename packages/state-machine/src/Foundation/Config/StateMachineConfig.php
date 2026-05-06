<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Config;

use PhpArchitecture\Graph\Config\GraphConfig;
use PhpArchitecture\StateMachine\Foundation\Config\Exception\InvalidStateMachineConfigException;
use PhpArchitecture\StateMachine\Foundation\Pointer\Strategy\Default\AllPointersUntilBlockedStrategy;
use PhpArchitecture\StateMachine\Foundation\Pointer\Strategy\PointersSelectionStrategy;
use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\Default\ForkTransitionStrategy;
use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\Default\RejectStrategy;
use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\Default\SingleTransitionStrategy;
use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\Default\WaitAndForkStrategy;
use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\Default\WaitStrategy;
use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\TransitionStrategy;
use PhpArchitecture\Technical\Assert;

final readonly class StateMachineConfig
{
    /**
     * @param TransitionStrategy[] $transitionStrategies
     */
    public function __construct(
        public bool $allowCycles = true,
        public bool $allowSelfLoops = true,
        public bool $allowParallelTransitions = true,
        public array $transitionStrategies = [
            new WaitAndForkStrategy(),
            new WaitStrategy(),
            new SingleTransitionStrategy(),
            new ForkTransitionStrategy(),
            new RejectStrategy(),
        ],
        public PointersSelectionStrategy $pointersSelectionStrategy = new AllPointersUntilBlockedStrategy(),
    ) {
        Assert::eachInstanceOf($this->transitionStrategies, TransitionStrategy::class, InvalidStateMachineConfigException::class);
    }

    public function toGraphConfig(): GraphConfig
    {
        return new GraphConfig(
            allowSelfLoop: $this->allowSelfLoops,
            allowMultiEdge: $this->allowParallelTransitions,
            allowCyclicEdge: $this->allowCycles,
        );
    }
}
