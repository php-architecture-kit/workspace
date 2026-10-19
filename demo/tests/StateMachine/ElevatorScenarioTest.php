<?php

declare(strict_types=1);

namespace PhpArchitecture\Demo\Tests\StateMachine;

use PhpArchitecture\Demo\StateMachine\Domain\Elevator\ElevatorMachine;

final class ElevatorScenarioTest extends ScenarioTestCase
{
    private const int RIDE = ElevatorMachine::RIDE_SECONDS;
    private const int DOORS = ElevatorMachine::DOORS_SECONDS;

    protected function scenario(): string
    {
        return 'elevator';
    }

    public function testRidesToTheCallOpensTheDoorsAndGoesIdleAgain(): void
    {
        $snapshot = $this->act('hallCall', ['floor' => 3, 'direction' => 'down']);
        self::assertSame(['demo.elevator.ride'], self::at($snapshot));
        self::assertTrue($snapshot['panel']['car']['moving']);

        $this->tick(self::RIDE);
        $this->tick(self::RIDE);
        $snapshot = $this->tick(self::RIDE);
        self::assertSame(3, $snapshot['panel']['car']['floor']);
        self::assertSame('open', $snapshot['panel']['car']['doors']);
        self::assertSame([], $snapshot['panel']['calls']['hall']);

        $snapshot = $this->tick(self::DOORS);
        self::assertSame('closed', $snapshot['panel']['car']['doors']);
        self::assertSame(['demo.elevator.idle'], self::at($snapshot));
    }

    public function testEveryLapGetsAFullDoorWindow(): void
    {
        $this->act('hallCall', ['floor' => 1, 'direction' => 'up']);
        $snapshot = $this->tick(self::RIDE);
        self::assertSame('open', $snapshot['panel']['car']['doors']);

        // the photocell keeps the doors open: closing reopens them and a new window starts
        $this->act('obstruction', ['on' => true]);
        $snapshot = $this->tick(self::DOORS);
        self::assertContains('demo.elevator.close-doors', $snapshot['trail']);
        self::assertSame('open', $snapshot['panel']['car']['doors']);

        $this->act('obstruction', ['on' => false]);
        $snapshot = $this->tick(self::DOORS - 1);
        self::assertSame('open', $snapshot['panel']['car']['doors'], 'the second window is as long as the first');

        $snapshot = $this->tick(1);
        self::assertSame('closed', $snapshot['panel']['car']['doors']);
        self::assertSame(['demo.elevator.idle'], self::at($snapshot), 'no pointer of a reopened window is left behind');
    }

    public function testCloseButtonWinsTheRaceAgainstTheDoorTimer(): void
    {
        $this->act('carCall', ['floor' => 0]);
        $snapshot = $this->act('closeButton');
        self::assertSame('closed', $snapshot['panel']['car']['doors']);
        self::assertSame(['demo.elevator.idle', 'demo.elevator.door-timer'], self::at($snapshot), 'the losing timer still runs');

        $snapshot = $this->tick(self::DOORS);
        self::assertSame(['demo.elevator.idle'], self::at($snapshot), 'the race absorbs the late timer');
    }

    public function testEveryLapHasItsOwnRaceWinner(): void
    {
        // lap 1: "close" wins, its timer keeps running
        $this->act('carCall', ['floor' => 0]);
        $this->act('closeButton');

        // lap 2 opens the doors before the old timer ends: the timer wins this time
        $snapshot = $this->act('carCall', ['floor' => 0]);
        self::assertSame('open', $snapshot['panel']['car']['doors']);
        $snapshot = $this->tick(self::DOORS - 1);
        self::assertSame('open', $snapshot['panel']['car']['doors'], 'the late timer of lap 1 does not close lap 2');
        $snapshot = $this->tick(1);
        self::assertSame('closed', $snapshot['panel']['car']['doors']);
        self::assertSame(['demo.elevator.idle'], self::at($snapshot));

        // lap 3: "close" wins again
        $this->act('carCall', ['floor' => 0]);
        $snapshot = $this->act('closeButton');
        self::assertSame('closed', $snapshot['panel']['car']['doors']);
        $snapshot = $this->tick(self::DOORS);
        self::assertSame(['demo.elevator.idle'], self::at($snapshot));
    }

    public function testCollectsCallsInTheDirectionOfTravel(): void
    {
        $this->act('carCall', ['floor' => 4]);
        $this->act('hallCall', ['floor' => 2, 'direction' => 'up']);
        $this->act('hallCall', ['floor' => 1, 'direction' => 'down']);

        $this->tick(self::RIDE);
        $snapshot = $this->tick(self::RIDE);
        self::assertSame(2, $snapshot['panel']['car']['floor']);
        self::assertSame('open', $snapshot['panel']['car']['doors'], 'stops for "2 up" on the way up');
        self::assertSame(['1:down'], $snapshot['panel']['calls']['hall'], 'passed "1 down" in the other direction');

        $this->tick(self::DOORS);
        $this->tick(self::RIDE);
        $snapshot = $this->tick(self::RIDE);
        self::assertSame(4, $snapshot['panel']['car']['floor']);
        self::assertSame('open', $snapshot['panel']['car']['doors']);

        $this->tick(self::DOORS);
        $this->tick(self::RIDE);
        $this->tick(self::RIDE);
        $snapshot = $this->tick(self::RIDE);
        self::assertSame(1, $snapshot['panel']['car']['floor'], 'turns around for "1 down"');
        self::assertSame([], $snapshot['panel']['calls']['hall']);
    }

    public function testOverloadKeepsTheDoorsOpenUntilSomeoneGetsOut(): void
    {
        $this->act('hallCall', ['floor' => 0, 'direction' => 'up']);
        $this->act('passengers', ['delta' => 10]);
        $snapshot = $this->tick(self::DOORS);
        self::assertTrue($snapshot['panel']['car']['alarm']);
        self::assertSame(['demo.elevator.overload'], self::at($snapshot));

        $snapshot = $this->act('passengers', ['delta' => -3]);
        self::assertFalse($snapshot['panel']['car']['alarm']);
        self::assertSame(['demo.elevator.door-button', 'demo.elevator.door-timer'], self::at($snapshot), 'a new door window races again');
    }

    public function testStopHaltsTheRideUntilReleased(): void
    {
        $this->act('carCall', ['floor' => 2]);
        $snapshot = $this->act('stop', ['on' => true]);
        self::assertSame(['demo.elevator.emergency'], self::at($snapshot));
        self::assertTrue($snapshot['panel']['car']['halted']);

        $snapshot = $this->tick(10);
        self::assertSame(['demo.elevator.emergency'], self::at($snapshot));
        self::assertSame(0, $snapshot['panel']['car']['floor']);

        $this->act('stop', ['on' => false]);
        $this->tick(self::RIDE);
        $snapshot = $this->tick(self::RIDE);
        self::assertSame(2, $snapshot['panel']['car']['floor']);
    }
}
