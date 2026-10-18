<?php

declare(strict_types=1);

namespace PhpArchitecture\Demo\StateMachine\Domain\Atm\Task;

use PhpArchitecture\StateMachine\Foundation\Task\Task;

/** Compensation: the cash was not handed out, give the money back. */
final readonly class ReverseWithdrawal implements Task
{
    public function __construct(
        public string $cardNumber,
        public int $amount,
    ) {
    }
}
