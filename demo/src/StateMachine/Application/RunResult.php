<?php

declare(strict_types=1);

namespace PhpArchitecture\Demo\StateMachine\Application;

use PhpArchitecture\Demo\StateMachine\Domain\Shared\DemoStateMachine;

final readonly class RunResult
{
    /** @param list<string> $trail boxes entered during this request */
    public function __construct(
        public Scenario $scenario,
        public DemoStateMachine $machine,
        public ScenarioSession $session,
        public array $trail,
        public string $status,
    ) {
    }
}
