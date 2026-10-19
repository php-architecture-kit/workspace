<?php

declare(strict_types=1);

namespace PhpArchitecture\Demo\StateMachine\Domain\Elevator;

use PhpArchitecture\Demo\StateMachine\Domain\Shared\DemoStateMachine;
use PhpArchitecture\Demo\StateMachine\Domain\Shared\Step\StepNode;
use PhpArchitecture\Demo\StateMachine\Domain\Shared\World;
use PhpArchitecture\StateMachine\Foundation\Component\Await\AwaitStateComponent;
use PhpArchitecture\StateMachine\Foundation\Component\Choice\ChoiceComponent;
use PhpArchitecture\StateMachine\Foundation\Component\Race\RaceComponent;
use PhpArchitecture\StateMachine\Foundation\Execution\Execution;
use PhpArchitecture\StateMachine\Foundation\State\States;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\Output\TransitionConditionDecision;

/**
 * One elevator car - a single Execution that never ends: idle, ride, doors, again.
 *
 * Every lap re-enters the same components (the 2-second ride, the race between the "close" button
 * and the 5-second door timer), and the Execution is persisted between every request.
 */
final class ElevatorMachine extends DemoStateMachine
{
    public const string CAR = 'car';
    public const string CALLS = 'calls';
    public const string STOP = 'stop';
    public const string OBSTRUCTION = 'door_obstruction';
    public const string CLOSE_BUTTON = 'close_button';

    public const int TOP_FLOOR = 5;
    public const int CAPACITY = 8;
    public const int RIDE_SECONDS = 2;
    public const int DOORS_SECONDS = 5;

    private StepNode $idle;

    public function start(Execution $execution): void
    {
        $world = new World($execution->states);
        $world->put(self::CAR, [
            'floor' => 0,
            'direction' => 'idle',
            'doors' => 'closed',
            'moving' => false,
            'halted' => false,
            'load' => 0,
            'alarm' => false,
            'next' => null,
            'rideStartedAt' => null,
            'doorsOpenedAt' => null,
            'doorWindow' => 'over',
        ]);
        $world->put(self::CALLS, ['hall' => [], 'car' => []]);

        $execution->pointers->startAt($this->idle->id);
    }

    protected function define(): void
    {
        $this->idle = $this->step('demo.elevator.idle', 'Idle', static function (World $world): void {
            $world->put(self::CAR, ['direction' => 'idle', 'moving' => false]);
        });
        $choose = $this->step('demo.elevator.choose-direction', 'Choose direction', static function (World $world): void {
            $car = $world->get(self::CAR);
            $calls = $world->get(self::CALLS);
            [$next, $direction] = ScanPolicy::decide($car['floor'], $car['direction'], $calls['hall'], $calls['car']);
            $world->put(self::CAR, ['next' => $next, 'direction' => $direction]);
        });
        $depart = $this->step('demo.elevator.depart', 'Depart');
        $clock = $this->clock;
        $move = $this->step('demo.elevator.move', 'Ride one floor', static function (World $world) use ($clock): void {
            $world->put(self::CAR, ['moving' => true, 'halted' => false, 'rideStartedAt' => $clock->now()->getTimestamp()]);
        });
        $emergency = $this->step('demo.elevator.emergency', 'Emergency stop', static function (World $world): void {
            $world->put(self::CAR, ['moving' => false, 'halted' => true]);
        });
        $arrive = $this->step('demo.elevator.arrive', 'Reach floor', static function (World $world): void {
            $car = $world->get(self::CAR);
            $world->put(self::CAR, ['floor' => $car['floor'] + ($car['direction'] === 'up' ? 1 : -1), 'moving' => false]);
        });
        $openDoors = $this->step('demo.elevator.open-doors', 'Open doors', static function (World $world) use ($clock): void {
            $car = $world->get(self::CAR);
            $calls = $world->get(self::CALLS);
            [$hall, $carCalls] = ScanPolicy::serve($car['floor'], $car['direction'], $calls['hall'], $calls['car']);
            $world->put(self::CALLS, ['hall' => $hall, 'car' => $carCalls]);
            $world->put(self::CAR, ['doors' => 'open', 'alarm' => false, 'doorsOpenedAt' => $clock->now()->getTimestamp(), 'doorWindow' => 'open']);
        });
        $doorButton = $this->step('demo.elevator.door-button', 'Watch "close" button');
        $closePressed = $this->step('demo.elevator.close-pressed', '"Close" pressed');
        $doorTimer = $this->step('demo.elevator.door-timer', 'Door timer');
        $timeUp = $this->step('demo.elevator.time-up', '5 s passed');
        $closeDoors = $this->step('demo.elevator.close-doors', 'Close doors', static function (World $world): void {
            $world->remove(self::CLOSE_BUTTON);
            $world->put(self::CAR, ['doors' => 'closing', 'doorWindow' => 'over']);
        });
        $overload = $this->step('demo.elevator.overload', 'Overload', static function (World $world): void {
            $world->put(self::CAR, ['doors' => 'open', 'alarm' => true]);
        });
        $doorsClosed = $this->step('demo.elevator.doors-closed', 'Doors closed', static function (World $world): void {
            $world->put(self::CAR, ['doors' => 'closed', 'alarm' => false]);
        });

        $this->when($this->idle, $choose, static fn (World $world): bool => $world->get(self::CALLS)['hall'] !== [] || $world->get(self::CALLS)['car'] !== [], 'call');

        $next = static fn (string $branch): \Closure => static fn (States $states): TransitionConditionDecision => (new World($states))->value(self::CAR, 'next') === $branch
            ? TransitionConditionDecision::Accepted
            : TransitionConditionDecision::Rejected;
        $this->place(
            ChoiceComponent::create('elevator-direction', [
                ScanPolicy::HERE => $next(ScanPolicy::HERE),
                ScanPolicy::MOVE => $next(ScanPolicy::MOVE),
                ScanPolicy::NONE => $next(ScanPolicy::NONE),
            ]),
            'demo.elevator.choice', 'Choice',
            ['trigger' => $choose],
            [ScanPolicy::HERE => [$openDoors, 'call here'], ScanPolicy::MOVE => [$depart, 'call ahead'], ScanPolicy::NONE => [$this->idle, 'no calls']],
        );

        $notStopped = static fn (World $world): bool => !$world->has(self::STOP);
        $this->when($depart, $move, $notStopped, 'STOP not active');

        // the ride takes 2 seconds unless STOP is pressed first
        $this->place(
            AwaitStateComponent::create('elevator-ride', self::STOP, timeout: self::RIDE_SECONDS, clock: $this->clock),
            'demo.elevator.ride', 'Riding (2 s)',
            ['at' => $move],
            ['run' => [$emergency, 'STOP pressed'], 'expired' => [$arrive, '2 s passed']],
        );
        $this->when($emergency, $move, $notStopped, 'STOP released');

        $this->routes($arrive, static function (World $world): string {
            $car = $world->get(self::CAR);
            $calls = $world->get(self::CALLS);

            return ScanPolicy::onArrival($car['floor'], $car['direction'], $calls['hall'], $calls['car']);
        }, [
            ScanPolicy::OPEN => [$openDoors, 'stop here'],
            ScanPolicy::PASS => [$move, 'pass by'],
            ScanPolicy::HALT => [$choose, 'nothing ahead'],
        ]);

        // the doors stay open until the "close" button or the 5-second timer, whichever comes first;
        // both branches always reach the race (the button branch also when the window is over),
        // so the loser of every lap is absorbed and the next lap starts clean
        $this->then($openDoors, $doorButton);
        $this->then($openDoors, $doorTimer);
        $this->when($doorButton, $closePressed, static fn (World $world): bool => $world->has(self::CLOSE_BUTTON)
            || $world->value(self::CAR, 'doorWindow') !== 'open', '"close" pressed');
        $this->when($doorTimer, $timeUp, static fn (World $world): bool => $clock->now()->getTimestamp()
            >= $world->value(self::CAR, 'doorsOpenedAt') + self::DOORS_SECONDS, '5 s passed');
        $this->place(
            RaceComponent::create('elevator-doors', ['button', 'timer']),
            'demo.elevator.doors', 'Race: first closes',
            ['button' => $closePressed, 'timer' => $timeUp],
            ['winner' => [$closeDoors, 'first wins']],
        );
        $this->routes($closeDoors, static fn (World $world): string => match (true) {
            $world->has(self::OBSTRUCTION) => 'obstructed',
            $world->value(self::CAR, 'load') > self::CAPACITY => 'overload',
            default => 'clear',
        }, [
            'obstructed' => [$openDoors, 'photocell'],
            'overload' => [$overload, 'overload'],
            'clear' => [$doorsClosed, 'clear'],
        ]);
        $this->when($overload, $openDoors, static fn (World $world): bool => $world->value(self::CAR, 'load') <= self::CAPACITY, 'load OK');
        $this->then($doorsClosed, $choose);
    }
}
