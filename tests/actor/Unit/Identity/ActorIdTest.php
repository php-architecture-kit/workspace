<?php

declare(strict_types=1);

namespace Tests\PhpArchitecture\Actor\Unit\Identity;

use PhpArchitecture\Actor\Identity\ActorId;
use PhpArchitecture\Uuid\Uuid;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ActorIdTest extends TestCase
{
    #[Test]
    public function extendsUuid(): void
    {
        $id = ActorId::fromString('df516cba-fb13-4f45-8335-00252f1b87e2');

        $this->assertInstanceOf(Uuid::class, $id);
    }

    #[Test]
    public function fromStringCreatesActorId(): void
    {
        $id = ActorId::fromString('df516cba-fb13-4f45-8335-00252f1b87e2');

        $this->assertInstanceOf(ActorId::class, $id);
        $this->assertSame('df516cba-fb13-4f45-8335-00252f1b87e2', $id->value());
    }

    #[Test]
    public function fromUuidCreatesActorId(): void
    {
        $uuid = Uuid::fromString('df516cba-fb13-4f45-8335-00252f1b87e2');
        $id = ActorId::fromUuid($uuid);

        $this->assertInstanceOf(ActorId::class, $id);
        $this->assertSame($uuid->value(), $id->value());
    }

    #[Test]
    public function newCreatesActorId(): void
    {
        $id = ActorId::new();

        $this->assertInstanceOf(ActorId::class, $id);
        $this->assertTrue($id->validate());
    }

    #[Test]
    public function toStringReturnsUuidString(): void
    {
        $id = ActorId::fromString('df516cba-fb13-4f45-8335-00252f1b87e2');

        $this->assertSame('df516cba-fb13-4f45-8335-00252f1b87e2', $id->toString());
    }

    #[Test]
    public function equalsWorksCorrectly(): void
    {
        $id1 = ActorId::fromString('df516cba-fb13-4f45-8335-00252f1b87e2');
        $id2 = ActorId::fromString('df516cba-fb13-4f45-8335-00252f1b87e2');
        $id3 = ActorId::fromString('00000000-0000-0000-0000-000000000000');

        $this->assertTrue($id1->equals($id2));
        $this->assertFalse($id1->equals($id3));
    }
}
