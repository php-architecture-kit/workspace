<?php

declare(strict_types=1);

namespace PhpArchitecture\Demo\StateMachine\Domain\Atm\Bank;

final readonly class BankAnswer
{
    private function __construct(
        public bool $approved,
        public string $reason,
    ) {
    }

    public static function approved(string $reason): self
    {
        return new self(true, $reason);
    }

    public static function declined(string $reason): self
    {
        return new self(false, $reason);
    }
}
