<?php

declare(strict_types=1);

namespace Tests\PhpArchitecture\Clock\Unit;

use PhpArchitecture\Clock\LocalizedClock;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

class LocalizedClockTest extends TestCase
{
    #[Test]
    public function implementsClockInterface(): void
    {
        $clock = new LocalizedClock(new \DateTimeZone('UTC'));

        $this->assertInstanceOf(ClockInterface::class, $clock);
    }

    #[Test]
    public function nowReturnsDateTimeImmutable(): void
    {
        $clock = new LocalizedClock(new \DateTimeZone('UTC'));

        $this->assertInstanceOf(\DateTimeImmutable::class, $clock->now());
    }

    #[Test]
    public function nowReturnsTimeInSpecifiedTimezone(): void
    {
        $timezone = new \DateTimeZone('Europe/Warsaw');
        $clock = new LocalizedClock($timezone);

        $now = $clock->now();

        $this->assertSame('Europe/Warsaw', $now->getTimezone()->getName());
    }

    #[Test]
    public function utcFactoryCreatesUtcClock(): void
    {
        $clock = LocalizedClock::utc();

        $now = $clock->now();

        $this->assertSame('UTC', $now->getTimezone()->getName());
    }

    #[Test]
    public function differentTimezonesReturnDifferentOffsets(): void
    {
        $utcClock = new LocalizedClock(new \DateTimeZone('UTC'));
        $tokyoClock = new LocalizedClock(new \DateTimeZone('Asia/Tokyo'));

        $utcNow = $utcClock->now();
        $tokyoNow = $tokyoClock->now();

        // Tokyo is UTC+9, so offset should be 9 hours = 32400 seconds
        $utcOffset = $utcNow->getOffset();
        $tokyoOffset = $tokyoNow->getOffset();

        $this->assertSame(0, $utcOffset);
        $this->assertSame(32400, $tokyoOffset); // 9 hours in seconds
    }

    #[Test]
    public function nowReturnsCurrentTime(): void
    {
        $clock = LocalizedClock::utc();
        $before = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $now = $clock->now();

        $after = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $this->assertGreaterThanOrEqual($before->getTimestamp(), $now->getTimestamp());
        $this->assertLessThanOrEqual($after->getTimestamp(), $now->getTimestamp());
    }

    #[Test]
    public function nowReturnsNewInstanceEachCall(): void
    {
        $clock = LocalizedClock::utc();

        $now1 = $clock->now();
        usleep(1000);
        $now2 = $clock->now();

        $this->assertNotSame($now1, $now2);
    }

    #[Test]
    public function supportsVariousTimezones(): void
    {
        $timezones = [
            'America/New_York',
            'Europe/London',
            'Asia/Shanghai',
            'Australia/Sydney',
            'Pacific/Auckland',
        ];

        foreach ($timezones as $tz) {
            $clock = new LocalizedClock(new \DateTimeZone($tz));
            $now = $clock->now();

            $this->assertSame($tz, $now->getTimezone()->getName());
        }
    }
}
