<?php

declare(strict_types=1);

namespace Tests\PhpArchitecture\DomainCore\Unit;

use PhpArchitecture\DomainCore\AggregateRoot;
use PhpArchitecture\DomainCore\DomainEvent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TestableAggregateRoot extends AggregateRoot
{
    public function doSomethingThatRecordsEvent(DomainEvent $event): void
    {
        $this->recordEvent($event);
    }
}

class AggregateRootTest extends TestCase
{
    #[Test]
    public function getEventsReturnsEmptyArrayByDefault(): void
    {
        $aggregate = new TestableAggregateRoot();

        $this->assertSame([], $aggregate->getEvents());
    }

    #[Test]
    public function recordEventAddsEventToList(): void
    {
        $event = $this->createMock(DomainEvent::class);
        $aggregate = new TestableAggregateRoot();

        $aggregate->doSomethingThatRecordsEvent($event);

        $this->assertCount(1, $aggregate->getEvents());
        $this->assertSame($event, $aggregate->getEvents()[0]);
    }

    #[Test]
    public function recordEventPreservesOrder(): void
    {
        $event1 = $this->createMock(DomainEvent::class);
        $event2 = $this->createMock(DomainEvent::class);
        $event3 = $this->createMock(DomainEvent::class);

        $aggregate = new TestableAggregateRoot();

        $aggregate->doSomethingThatRecordsEvent($event1);
        $aggregate->doSomethingThatRecordsEvent($event2);
        $aggregate->doSomethingThatRecordsEvent($event3);

        $events = $aggregate->getEvents();

        $this->assertCount(3, $events);
        $this->assertSame($event1, $events[0]);
        $this->assertSame($event2, $events[1]);
        $this->assertSame($event3, $events[2]);
    }

    #[Test]
    public function releaseEventsReturnsAndClearsEvents(): void
    {
        $event1 = $this->createMock(DomainEvent::class);
        $event2 = $this->createMock(DomainEvent::class);

        $aggregate = new TestableAggregateRoot();

        $aggregate->doSomethingThatRecordsEvent($event1);
        $aggregate->doSomethingThatRecordsEvent($event2);

        $releasedEvents = $aggregate->releaseEvents();

        $this->assertCount(2, $releasedEvents);
        $this->assertSame($event1, $releasedEvents[0]);
        $this->assertSame($event2, $releasedEvents[1]);
        $this->assertSame([], $aggregate->getEvents());
    }

    #[Test]
    public function releaseEventsReturnsEmptyArrayWhenNoEvents(): void
    {
        $aggregate = new TestableAggregateRoot();

        $this->assertSame([], $aggregate->releaseEvents());
    }

    #[Test]
    public function multipleReleaseEventsCallsReturnEmptyAfterFirst(): void
    {
        $event = $this->createMock(DomainEvent::class);

        $aggregate = new TestableAggregateRoot();

        $aggregate->doSomethingThatRecordsEvent($event);

        $firstRelease = $aggregate->releaseEvents();
        $secondRelease = $aggregate->releaseEvents();

        $this->assertCount(1, $firstRelease);
        $this->assertSame([], $secondRelease);
    }

    #[Test]
    public function getEventsDoesNotClearEvents(): void
    {
        $event = $this->createMock(DomainEvent::class);

        $aggregate = new TestableAggregateRoot();

        $aggregate->doSomethingThatRecordsEvent($event);

        $firstGet = $aggregate->getEvents();
        $secondGet = $aggregate->getEvents();

        $this->assertCount(1, $firstGet);
        $this->assertCount(1, $secondGet);
        $this->assertSame($firstGet, $secondGet);
    }

    #[Test]
    public function eventsCanBeRecordedAfterRelease(): void
    {
        $event1 = $this->createMock(DomainEvent::class);
        $event2 = $this->createMock(DomainEvent::class);

        $aggregate = new TestableAggregateRoot();

        $aggregate->doSomethingThatRecordsEvent($event1);
        $aggregate->releaseEvents();
        $aggregate->doSomethingThatRecordsEvent($event2);

        $events = $aggregate->getEvents();

        $this->assertCount(1, $events);
        $this->assertSame($event2, $events[0]);
    }
}
