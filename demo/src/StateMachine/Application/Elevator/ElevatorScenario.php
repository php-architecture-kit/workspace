<?php

declare(strict_types=1);

namespace PhpArchitecture\Demo\StateMachine\Application\Elevator;

use PhpArchitecture\Demo\StateMachine\Application\Exception\ActionRejected;
use PhpArchitecture\Demo\StateMachine\Application\Scenario;
use PhpArchitecture\Demo\StateMachine\Application\ScenarioSession;
use PhpArchitecture\Clock\ManualClock;
use PhpArchitecture\Demo\StateMachine\Domain\Elevator\ElevatorMachine;
use PhpArchitecture\Demo\StateMachine\Domain\Shared\DemoStateMachine;
use PhpArchitecture\StateMachine\Foundation\State\States;
use PhpArchitecture\StateMachine\Foundation\Execution\Execution;
use PhpArchitecture\StateMachine\Foundation\Task\Bus\TaskBusInterface;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;

/** Passengers and the safety devices around ElevatorMachine. */
final class ElevatorScenario implements Scenario
{
    public function id(): string
    {
        return 'elevator';
    }

    public function title(): string
    {
        return 'Elevator control';
    }

    public function machine(ContainerInterface $handlers, ClockInterface $clock, TaskBusInterface $taskBus): DemoStateMachine
    {
        return new ElevatorMachine($handlers, $clock, $taskBus);
    }

    public function newSession(DemoStateMachine $machine, ManualClock $clock, ?ScenarioSession $previous): ScenarioSession
    {
        $session = new ScenarioSession(Execution::create(), $clock->now());
        $session->note('actor', 'the elevator is switched on');
        $machine->start($session->execution);

        return $session;
    }

    public function act(string $action, array $payload, ScenarioSession $session): void
    {
        $states = $session->execution->states;
        $car = $states->getState(ElevatorMachine::CAR)?->values() ?? [];
        $calls = $states->getState(ElevatorMachine::CALLS)?->values() ?? [];

        switch ($action) {
            case 'hallCall':
                $floor = $this->floor($payload);
                $direction = (string) ($payload['direction'] ?? '');
                if (!in_array($direction, ['up', 'down'], true) || ($direction === 'up' && $floor === ElevatorMachine::TOP_FLOOR) || ($direction === 'down' && $floor === 0)) {
                    throw new ActionRejected('There is no such button.');
                }
                $call = "{$floor}:{$direction}";
                if (!in_array($call, $calls['hall'], true)) {
                    $states->defineOrModifyState(ElevatorMachine::CALLS, ['hall' => [...$calls['hall'], $call]]);
                }
                $session->note('actor', sprintf('someone on floor %d presses %s', $floor, $direction === 'up' ? '▲' : '▼'));
                break;
            case 'carCall':
                $floor = $this->floor($payload);
                if (!in_array($floor, $calls['car'], true)) {
                    $states->defineOrModifyState(ElevatorMachine::CALLS, ['car' => [...$calls['car'], $floor]]);
                }
                $session->note('actor', "a passenger presses {$floor} in the car");
                break;
            case 'closeButton':
                if ($car['doors'] !== 'open') {
                    throw new ActionRejected('The doors are not open.');
                }
                $states->defineOrModifyState(ElevatorMachine::CLOSE_BUTTON, ['pressed' => true]);
                $session->note('actor', 'a passenger presses "close doors"');
                break;
            case 'obstruction':
                $this->toggle($states, ElevatorMachine::OBSTRUCTION, ($payload['on'] ?? false) === true);
                $session->note('actor', ($payload['on'] ?? false) === true ? 'something blocks the doors' : 'the doorway is clear');
                break;
            case 'passengers':
                if ($car['doors'] !== 'open' || $car['moving']) {
                    throw new ActionRejected('Passengers can only get in or out through open doors.');
                }
                $delta = (int) ($payload['delta'] ?? 0);
                $states->defineOrModifyState(ElevatorMachine::CAR, ['load' => max(0, $car['load'] + $delta)]);
                $session->note('actor', $delta > 0 ? "{$delta} passenger(s) get in" : abs($delta) . ' passenger(s) get out');
                break;
            case 'stop':
                $this->toggle($states, ElevatorMachine::STOP, ($payload['on'] ?? false) === true);
                $session->note('actor', ($payload['on'] ?? false) === true ? 'STOP is pressed' : 'STOP is released');
                break;
            default:
                throw new ActionRejected("Unknown action '{$action}'.");
        }
    }

    public function react(ScenarioSession $session): bool
    {
        return false;
    }

    public function panel(ScenarioSession $session): array
    {
        $states = $session->execution->states;
        $car = $states->getState(ElevatorMachine::CAR)?->values() ?? [];

        // how far the car got between two floors; the ride component expires after RIDE_SECONDS
        $progress = $car['moving'] && $car['rideStartedAt'] !== null
            ? min(1, ($session->time->getTimestamp() - $car['rideStartedAt']) / ElevatorMachine::RIDE_SECONDS)
            : 0;

        return [
            'car' => $car + ['progress' => $progress],
            'calls' => $states->getState(ElevatorMachine::CALLS)?->values() ?? [],
            'stop' => $states->getState(ElevatorMachine::STOP) !== null,
            'obstruction' => $states->getState(ElevatorMachine::OBSTRUCTION) !== null,
            'floors' => ElevatorMachine::TOP_FLOOR + 1,
            'capacity' => ElevatorMachine::CAPACITY,
            'timing' => ['ride' => ElevatorMachine::RIDE_SECONDS, 'doors' => ElevatorMachine::DOORS_SECONDS],
        ];
    }

    /** @param array<string,mixed> $payload */
    private function floor(array $payload): int
    {
        $floor = (int) ($payload['floor'] ?? -1);
        if ($floor < 0 || $floor > ElevatorMachine::TOP_FLOOR) {
            throw new ActionRejected('There is no such floor.');
        }

        return $floor;
    }

    private function toggle(States $states, string $state, bool $on): void
    {
        $on ? $states->defineOrModifyState($state, ['active' => true]) : $states->removeStateByName($state);
    }
}
