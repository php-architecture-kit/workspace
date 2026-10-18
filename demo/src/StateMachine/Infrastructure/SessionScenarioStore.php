<?php

declare(strict_types=1);

namespace PhpArchitecture\Demo\StateMachine\Infrastructure;

use PhpArchitecture\Demo\StateMachine\Application\ScenarioSession;
use PhpArchitecture\Demo\StateMachine\Application\ScenarioStore;
use Symfony\Component\HttpFoundation\RequestStack;

/** Keeps each scenario (with its serialized Execution) in the visitor's HTTP session. */
final readonly class SessionScenarioStore implements ScenarioStore
{
    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    public function load(string $scenarioId): ?ScenarioSession
    {
        $stored = $this->requestStack->getSession()->get($this->key($scenarioId));
        if (!is_string($stored)) {
            return null;
        }

        $session = @unserialize($stored);

        // a session written by an older build of the demo is simply started over
        return $session instanceof ScenarioSession ? $session : null;
    }

    public function save(string $scenarioId, ScenarioSession $session): void
    {
        $this->requestStack->getSession()->set($this->key($scenarioId), serialize($session));
    }

    private function key(string $scenarioId): string
    {
        return "state_machine.{$scenarioId}";
    }
}
