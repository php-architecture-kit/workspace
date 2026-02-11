<?php

declare(strict_types=1);

namespace Tests\PhpArchitecture\Clock\Unit;

use PhpArchitecture\Clock\FrozenClock;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

class FrozenClockTest extends TestCase
{
    #[Test]
    public function implementsClockInterface(): void
    {
        $clock = new FrozenClock(new \DateTimeImmutable());

        $this->assertInstanceOf(ClockInterface::class, $clock);
    }

    #[Test]
    public function nowReturnsDateTimeImmutable(): void
    {
        $clock = new FrozenClock(new \DateTimeImmutable());

        $this->assertInstanceOf(\DateTimeImmutable::class, $clock->now());
    }

    #[Test]
    public function nowAlwaysReturnsSameTime(): void
    {
        $frozenTime = new \DateTimeImmutable('2024-06-15 12:00:00');
        $clock = new FrozenClock($frozenTime);

        $now1 = $clock->now();
        usleep(10000); // 10ms
        $now2 = $clock->now();

        $this->assertEquals($frozenTime, $now1);
        $this->assertEquals($frozenTime, $now2);
        $this->assertSame($now1, $now2);
    }

    #[Test]
    public function atFactoryCreatesFrozenClock(): void
    {
        $frozenTime = new \DateTimeImmutable('2024-01-01 00:00:00');
        $clock = FrozenClock::at($frozenTime);

        $this->assertEquals($frozenTime, $clock->now());
    }

    #[Test]
    public function fromNowFactoryFreezesCurrentTime(): void
    {
        $before = new \DateTimeImmutable();
        $clock = FrozenClock::fromNow();
        $after = new \DateTimeImmutable();

        $frozenTime = $clock->now();

        $this->assertGreaterThanOrEqual($before->getTimestamp(), $frozenTime->getTimestamp());
        $this->assertLessThanOrEqual($after->getTimestamp(), $frozenTime->getTimestamp());
    }

    #[Test]
    public function fromNowRemainsFrozenAfterTime(): void
    {
        $clock = FrozenClock::fromNow();
        $firstCall = $clock->now();

        usleep(10000); // 10ms

        $secondCall = $clock->now();

        $this->assertSame($firstCall, $secondCall);
    }

    #[Test]
    public function preservesTimezone(): void
    {
        $frozenTime = new \DateTimeImmutable('2024-06-15 12:00:00', new \DateTimeZone('Europe/Warsaw'));
        $clock = new FrozenClock($frozenTime);

        $now = $clock->now();

        $this->assertSame('Europe/Warsaw', $now->getTimezone()->getName());
    }

    #[Test]
    public function preservesMicroseconds(): void
    {
        $frozenTime = \DateTimeImmutable::createFromFormat('U.u', '1718450400.123456');
        $clock = new FrozenClock($frozenTime);

        $now = $clock->now();

        $this->assertSame($frozenTime->format('u'), $now->format('u'));
    }

    #[Test]
    public function canBeUsedForDeterministicTesting(): void
    {
        $testTime = new \DateTimeImmutable('2024-12-25 10:30:00');
        $clock = FrozenClock::at($testTime);

        // Simulate using clock in application code
        $result = $this->someServiceMethod($clock);

        $this->assertSame('2024-12-25', $result);
    }

    private function someServiceMethod(ClockInterface $clock): string
    {
        return $clock->now()->format('Y-m-d');
    }
}
