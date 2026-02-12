<?php

declare(strict_types=1);

namespace Tests\PhpArchitecture\DomainCore\Unit;

use PhpArchitecture\DomainCore\DomainEvent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DomainEventTest extends TestCase
{
    #[Test]
    public function domainEventIsInterface(): void
    {
        $reflection = new \ReflectionClass(DomainEvent::class);

        $this->assertTrue($reflection->isInterface());
    }

    #[Test]
    public function classCanImplementDomainEvent(): void
    {
        $event = new class implements DomainEvent {};

        $this->assertInstanceOf(DomainEvent::class, $event);
    }

    #[Test]
    public function domainEventCanHaveCustomProperties(): void
    {
        $event = new class('test-id', 'test-data') implements DomainEvent {
            public function __construct(
                public readonly string $aggregateId,
                public readonly string $payload,
            ) {}
        };

        $this->assertSame('test-id', $event->aggregateId);
        $this->assertSame('test-data', $event->payload);
    }
}
