<?php

declare(strict_types=1);

namespace Tests\PhpArchitecture\Clock\Unit;

use PhpArchitecture\Clock\SystemClock;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

class SystemClockTest extends TestCase
{
    #[Test]
    public function implementsClockInterface(): void
    {
        $clock = new SystemClock();

        $this->assertInstanceOf(ClockInterface::class, $clock);
    }

    #[Test]
    public function nowReturnsDateTimeImmutable(): void
    {
        $clock = new SystemClock();

        $this->assertInstanceOf(\DateTimeImmutable::class, $clock->now());
    }

    #[Test]
    public function nowReturnsCurrentTime(): void
    {
        $clock = new SystemClock();
        $before = new \DateTimeImmutable();

        $now = $clock->now();

        $after = new \DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before->getTimestamp(), $now->getTimestamp());
        $this->assertLessThanOrEqual($after->getTimestamp(), $now->getTimestamp());
    }

    #[Test]
    public function nowReturnsNewInstanceEachCall(): void
    {
        $clock = new SystemClock();

        $now1 = $clock->now();
        usleep(1000); // 1ms
        $now2 = $clock->now();

        $this->assertNotSame($now1, $now2);
    }

    #[Test]
    public function multipleInstancesReturnSimilarTime(): void
    {
        $clock1 = new SystemClock();
        $clock2 = new SystemClock();

        $now1 = $clock1->now();
        $now2 = $clock2->now();

        $diff = abs($now1->getTimestamp() - $now2->getTimestamp());

        $this->assertLessThanOrEqual(1, $diff);
    }
}
