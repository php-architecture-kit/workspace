<?php

declare(strict_types=1);

namespace PhpArchitecture\Demo\StateMachine\Presentation\View;

/**
 * Hand-placed positions of the boxes (grid cells), per scenario.
 * Every box of a machine must have a position - see GraphLayoutTest.
 */
final class GraphLayout
{
    /** @return array<string,array{0:float,1:float}> box => [column, row] */
    public function of(string $scenarioId): array
    {
        return match ($scenarioId) {
            'atm' => [
                'demo.atm.welcome' => [0, 0],
                'demo.atm.await-card' => [0, 1],
                'demo.atm.read-card' => [0, 2],
                'demo.atm.card-unreadable' => [1, 2],
                'demo.atm.ask-pin' => [0, 3],
                'demo.atm.await-pin' => [0, 4],
                'demo.atm.input-timeout' => [1, 4],
                'demo.atm.read-pin' => [0, 5],
                'demo.atm.cancelled' => [1, 5],
                'demo.atm.verify-pin' => [0, 6],
                'demo.atm.bank-verify-pin' => [0, 7],
                'demo.atm.wrong-pin' => [1, 7],
                'demo.atm.ask-amount' => [0, 8],
                'demo.atm.bank-unavailable' => [1, 8],
                'demo.atm.await-amount' => [0, 9],
                'demo.atm.read-amount' => [0, 10],
                'demo.atm.invalid-amount' => [1, 10],
                'demo.atm.authorize' => [0, 11],
                'demo.atm.bank-authorize' => [0, 12],
                'demo.atm.declined' => [1, 12],
                'demo.atm.eject-card' => [2, 6],
                'demo.atm.await-card-taken' => [2, 7],
                'demo.atm.retain-card' => [2, 8],
                'demo.atm.return-card' => [3, 0],
                'demo.atm.await-card-first' => [3, 1],
                'demo.atm.dispense' => [3, 2],
                'demo.atm.fork' => [3, 3],
                'demo.atm.count-cash' => [2, 4],
                'demo.atm.print-receipt' => [4, 4],
                'demo.atm.join' => [3, 5],
                'demo.atm.present-cash' => [3, 6],
                'demo.atm.await-cash-taken' => [3, 7],
                'demo.atm.goodbye' => [3, 12],
                'demo.atm.card-forgotten' => [4, 2],
                'demo.atm.retract-cash' => [4, 7],
                'demo.atm.reverse' => [4, 8],
                'demo.atm.bank-reverse' => [4, 9],
                'demo.atm.reversal-failed' => [4, 10],
            ],
            'elevator' => [
                'demo.elevator.idle' => [0, 0],
                'demo.elevator.choose-direction' => [1, 0],
                'demo.elevator.choice' => [2, 0],
                'demo.elevator.depart' => [3, 0],
                'demo.elevator.move' => [3, 1],
                'demo.elevator.ride' => [3, 2],
                'demo.elevator.emergency' => [4, 1],
                'demo.elevator.arrive' => [3, 3],
                'demo.elevator.open-doors' => [3, 4],
                'demo.elevator.doors' => [3, 5],
                'demo.elevator.close-doors' => [3, 6],
                'demo.elevator.overload' => [2, 6],
                'demo.elevator.doors-closed' => [1, 5],
            ],
            default => [],
        };
    }
}
