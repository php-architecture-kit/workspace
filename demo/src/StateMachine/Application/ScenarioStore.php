<?php

declare(strict_types=1);

namespace PhpArchitecture\Demo\StateMachine\Application;

interface ScenarioStore
{
    public function load(string $scenarioId): ?ScenarioSession;

    public function save(string $scenarioId, ScenarioSession $session): void;
}
