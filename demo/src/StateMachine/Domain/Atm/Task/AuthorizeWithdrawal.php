<?php

declare(strict_types=1);

namespace PhpArchitecture\Demo\StateMachine\Domain\Atm\Task;

use PhpArchitecture\StateMachine\Foundation\Task\Task;

final readonly class AuthorizeWithdrawal implements Task
{
    public function __construct(
        public string $cardNumber,
        public int $amount,
    ) {
    }
}
