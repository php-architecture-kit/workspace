<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Transition\Strategy\Output;

use PhpArchitecture\StateMachine\Foundation\Transition\Exception\InvalidTransitionException;
use PhpArchitecture\StateMachine\Foundation\Transition\Transition;
use PhpArchitecture\Technical\Assert;

final readonly class TransitionSelectionOutput
{
    /** 
     * @param Transition[] $goto
     * @param Transition[] $waitfor
     * @param Transition[] $reject
     */
    public function __construct(
        public array $goto,
        public array $waitfor,
        public array $reject,
    ) {
        Assert::eachInstanceOf($this->goto, Transition::class, InvalidTransitionException::class);
        Assert::eachInstanceOf($this->waitfor, Transition::class, InvalidTransitionException::class);
        Assert::eachInstanceOf($this->reject, Transition::class, InvalidTransitionException::class);
    }
}
