<?php

declare(strict_types=1);

namespace PhpArchitecture\Demo\StateMachine\Domain\Atm\Task;

use PhpArchitecture\StateMachine\Foundation\Task\Task;

final readonly class VerifyPin implements Task
{
    public function __construct(
        public string $cardNumber,
        public string $pin,
    ) {
    }
}
