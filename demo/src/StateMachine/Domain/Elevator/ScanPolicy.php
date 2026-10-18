<?php

declare(strict_types=1);

namespace PhpArchitecture\Demo\StateMachine\Domain\Elevator;

/**
 * Collective control (SCAN): keep the direction while there are calls ahead,
 * stop for car calls and for hall calls in the direction of travel, turn around at the end.
 *
 * Calls: car calls are floor numbers, hall calls are "{floor}:{up|down}".
 */
final class ScanPolicy
{
    public const string HERE = 'here';
    public const string MOVE = 'move';
    public const string NONE = 'none';

    public const string OPEN = 'open';
    public const string PASS = 'pass';
    public const string HALT = 'halt';

    /**
     * Where to go from a standing car.
     *
     * @param list<string> $hall
     * @param list<int>    $car
     *
     * @return array{0:string,1:string} [here|move|none, direction]
     */
    public static function decide(int $floor, string $direction, array $hall, array $car): array
    {
        if ($hall === [] && $car === []) {
            return [self::NONE, 'idle'];
        }

        if (self::servesHere($floor, $direction, $hall, $car)) {
            return [self::HERE, $direction];
        }

        if ($direction !== 'idle' && self::hasCallsBeyond($floor, $direction, $hall, $car)) {
            return [self::MOVE, $direction];
        }

        return [self::MOVE, self::hasCallsBeyond($floor, 'up', $hall, $car) ? 'up' : 'down'];
    }

    /**
     * What to do when the car reaches a floor while moving in the given direction.
     *
     * @param list<string> $hall
     * @param list<int>    $car
     */
    public static function onArrival(int $floor, string $direction, array $hall, array $car): string
    {
        if (self::servesHere($floor, $direction, $hall, $car)) {
            return self::OPEN;
        }

        return self::hasCallsBeyond($floor, $direction, $hall, $car) ? self::PASS : self::HALT;
    }

    /**
     * Calls answered by opening the doors at this floor.
     *
     * @param list<string> $hall
     * @param list<int>    $car
     *
     * @return array{0:list<string>,1:list<int>}
     */
    public static function serve(int $floor, string $direction, array $hall, array $car): array
    {
        $car = array_values(array_filter($car, static fn (int $call): bool => $call !== $floor));
        $endOfTravel = $direction === 'idle' || !self::hasCallsBeyond($floor, $direction, $hall, $car);
        $hall = array_values(array_filter(
            $hall,
            static fn (string $call): bool => !($call === "{$floor}:{$direction}" || ($endOfTravel && str_starts_with($call, "{$floor}:"))),
        ));

        return [$hall, $car];
    }

    /**
     * @param list<string> $hall
     * @param list<int>    $car
     */
    private static function servesHere(int $floor, string $direction, array $hall, array $car): bool
    {
        if (in_array($floor, $car, true) || in_array("{$floor}:{$direction}", $hall, true)) {
            return true;
        }

        $hallHere = array_filter($hall, static fn (string $call): bool => str_starts_with($call, "{$floor}:"));

        return $hallHere !== [] && ($direction === 'idle' || !self::hasCallsBeyond($floor, $direction, $hall, $car));
    }

    /**
     * @param list<string> $hall
     * @param list<int>    $car
     */
    private static function hasCallsBeyond(int $floor, string $direction, array $hall, array $car): bool
    {
        $floors = [...$car, ...array_map(static fn (string $call): int => (int) explode(':', $call)[0], $hall)];

        foreach ($floors as $call) {
            if (($direction === 'up' && $call > $floor) || ($direction === 'down' && $call < $floor)) {
                return true;
            }
        }

        return false;
    }
}
