<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\State;

use PhpArchitecture\StateMachine\Foundation\Execution\Identity\ExecutionId;
use PhpArchitecture\StateMachine\Foundation\State\Identity\StateId;
use PhpArchitecture\StateMachine\Foundation\State\Property\StateDetail;
use PhpArchitecture\Technical\ArrayTransformation;
use PhpArchitecture\Technical\Assert;

class State
{
    /**
     * @var array<string,StateDetail>
     */
    public readonly array $details;

    /** 
     * @param StateDetail[] $details
     */
    protected function __construct(
        public readonly ExecutionId $executionId,
        public readonly StateId $id,
        public readonly string $name,
        array $details,
    ) {
        Assert::eachInstanceOf($details, StateDetail::class);
        $this->details = ArrayTransformation::indexBy($details, static fn(StateDetail $detail) => $detail->name);
    }

    /** 
     * @param StateDetail[] $details
     */
    public static function create(
        ExecutionId $executionId,
        string $name,
        array $details,
    ): static {
        /** @phpstan-ignore-next-line */
        return new static(
            $executionId,
            StateId::new(),
            $name,
            $details,
        );
    }

    /** 
     * @param StateDetail[] $details
     */
    public static function recreate(
        ExecutionId $executionId,
        StateId $id,
        string $name,
        array $details,
    ): static {
        /** @phpstan-ignore-next-line */
        return new static(
            $executionId,
            $id,
            $name,
            $details,
        );
    }
}
