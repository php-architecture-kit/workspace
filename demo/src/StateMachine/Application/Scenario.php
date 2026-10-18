<?php

declare(strict_types=1);

namespace PhpArchitecture\Demo\StateMachine\Application;

use PhpArchitecture\Demo\StateMachine\Application\Exception\ActionRejected;
use PhpArchitecture\Demo\StateMachine\Domain\Shared\DemoStateMachine;
use PhpArchitecture\StateMachine\Foundation\Task\Bus\TaskBusInterface;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface Scenario
{
    public function id(): string;

    public function title(): string;

    public function machine(ContainerInterface $handlers, ClockInterface $clock, TaskBusInterface $taskBus): DemoStateMachine;

    /**
     * A new session; the previous one is given when the user restarts, so long-lived
     * outside systems (the bank) can survive.
     */
    public function newSession(DemoStateMachine $machine, SimulationClock $clock, ?ScenarioSession $previous): ScenarioSession;

    /**
     * An outside actor acts: writes states of the Execution (or of the simulated world around it).
     *
     * @param array<string,mixed> $payload
     *
     * @throws ActionRejected
     */
    public function act(string $action, array $payload, ScenarioSession $session): void;

    /** Outside systems react after the machine ran (e.g. the bank answers). Returns true if the machine should run again. */
    public function react(ScenarioSession $session): bool;

    /** @return array<string,mixed> data for the simulation panel */
    public function panel(ScenarioSession $session): array;
}
