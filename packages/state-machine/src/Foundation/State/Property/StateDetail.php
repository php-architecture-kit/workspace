<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\State\Property;

final readonly class StateDetail
{
    public function __construct(
        public readonly string $name,
        public readonly mixed $value,
    ) {}

    public function withValue(mixed $value): self
    {
        return new self($this->name, $value);
    }
}
