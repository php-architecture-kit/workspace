<?php

declare(strict_types=1);

namespace PhpArchitecture\Demo\StateMachine\Application;

use DateTimeImmutable;
use PhpArchitecture\Clock\ManualClock;
use PhpArchitecture\Demo\StateMachine\Application\Exception\ActionRejected;
use PhpArchitecture\Demo\StateMachine\Domain\Shared\DemoStateMachine;
use PhpArchitecture\StateMachine\Foundation\Execution\ExecutionStatus;
use PhpArchitecture\StateMachine\Foundation\Task\Bus\Default\DeferredTaskBus;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;

/**
 * One request of a scenario: load the persisted Execution, let an outside actor act,
 * drive the machine until every pointer waits (or the process ends), save.
 */
final class ScenarioRunner
{
    public const string ACTION_RESET = 'reset';
    public const string ACTION_TICK = 'tick';

    /** Guard against a definition that cycles without waiting. */
    private const int MAX_STEPS = 500;

    /** @var array<string,Scenario> */
    private array $scenarios = [];

    /** @param iterable<Scenario> $scenarios */
    public function __construct(
        #[AutowireLocator('demo.state_machine.node_handler')]
        private readonly ContainerInterface $handlers,
        #[AutowireIterator(Scenario::class)]
        iterable $scenarios,
        private readonly ScenarioStore $store,
        private readonly EventJournal $journal,
    ) {
        foreach ($scenarios as $scenario) {
            $this->scenarios[$scenario->id()] = $scenario;
        }
    }

    /** @return array<string,Scenario> */
    public function scenarios(): array
    {
        return $this->scenarios;
    }

    /**
     * @param array<string,mixed> $payload
     *
     * @throws ActionRejected
     */
    public function run(string $scenarioId, ?string $action = null, array $payload = []): RunResult
    {
        $scenario = $this->scenarios[$scenarioId] ?? throw new RuntimeException("Unknown scenario '{$scenarioId}'.");
        $stored = $this->store->load($scenarioId);

        $clock = ManualClock::at($stored?->time ?? new DateTimeImmutable('2026-01-05 09:00:00'));
        $taskBus = new DeferredTaskBus($clock);
        $machine = $scenario->machine($this->handlers, $clock, $taskBus);

        $session = $stored;
        if ($session === null || $action === self::ACTION_RESET) {
            $session = $scenario->newSession($machine, $clock, $stored);
        } elseif ($action === self::ACTION_TICK) {
            $clock->advanceSeconds(max(1, min(120, (int) ($payload['seconds'] ?? 1))));
            $session->time = $clock->now();
        } elseif ($action !== null) {
            $scenario->act($action, $payload, $session);
        }

        $trail = $this->drive($machine, $session, $taskBus);
        for ($round = 0; $round < 5 && $scenario->react($session); $round++) {
            $trail = [...$trail, ...$this->drive($machine, $session, $taskBus)];
        }

        $this->store->save($scenarioId, $session);

        return new RunResult($scenario, $machine, $session, $trail, $this->status($session));
    }

    /** @return list<string> */
    private function drive(DemoStateMachine $machine, ScenarioSession $session, DeferredTaskBus $taskBus): array
    {
        $trail = $this->journal->record($session, $machine);

        for ($step = 0; $step < self::MAX_STEPS; $step++) {
            $status = $machine->execute($session->execution, 1);
            $trail = [...$trail, ...$this->journal->record($session, $machine)];
            if ($status !== ExecutionStatus::Running) {
                break;
            }
        }

        foreach ($taskBus->defferedTasks as $envelope) {
            if (!isset($session->dispatched[$envelope->id->toString()])) {
                $session->dispatched[$envelope->id->toString()] = true;
                $session->inbox[] = $envelope;
                $session->note('task', sprintf('task %s dispatched', (new \ReflectionClass($envelope->task))->getShortName()));
            }
        }

        return $trail;
    }

    private function status(ScenarioSession $session): string
    {
        return $session->execution->pointers->pointers === [] ? 'completed' : 'waiting';
    }
}
