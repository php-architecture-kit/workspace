<?php

declare(strict_types=1);

namespace PhpArchitecture\Demo\StateMachine\Application;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/** Simulated time of a scenario: it only moves when the user lets it ("+1 s", "+30 s", auto play). */
final class SimulationClock implements ClockInterface
{
    public function __construct(
        private DateTimeImmutable $now,
    ) {
    }

    public function advance(int $seconds): void
    {
        $this->now = $this->now->modify("+{$seconds} seconds");
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
