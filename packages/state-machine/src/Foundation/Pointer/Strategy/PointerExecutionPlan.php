<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Pointer\Strategy;

use PhpArchitecture\StateMachine\Foundation\Pointer\Pointer;

final readonly class PointerExecutionPlan
{
    public function __construct(
        public Pointer $pointer,
        public int $maxSteps,
    ) {}

    public static function step(Pointer $pointer): self
    {
        return new self($pointer, 1);
    }

    public static function untilBlocked(Pointer $pointer): self
    {
        return new self($pointer, PHP_INT_MAX);
    }

    public static function maxSteps(Pointer $pointer, int $steps): self
    {
        return new self($pointer, $steps);
    }
}
