<?php

declare(strict_types=1);

namespace Tests\PhpArchitecture\Actor\Unit;

use PhpArchitecture\Actor\Actor;
use PhpArchitecture\Actor\IdentifiedActor;
use PhpArchitecture\Actor\Identity\ActorId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class IdentifiedActorTest extends TestCase
{
    #[Test]
    public function implementsActorInterface(): void
    {
        $id = ActorId::fromString('df516cba-fb13-4f45-8335-00252f1b87e2');
        $actor = new IdentifiedActor($id);

        $this->assertInstanceOf(Actor::class, $actor);
    }

    #[Test]
    public function identifierReturnsUuidString(): void
    {
        $id = ActorId::fromString('df516cba-fb13-4f45-8335-00252f1b87e2');
        $actor = new IdentifiedActor($id);

        $this->assertSame('df516cba-fb13-4f45-8335-00252f1b87e2', $actor->identifier());
    }

    #[Test]
    public function differentIdsProduceDifferentIdentifiers(): void
    {
        $actor1 = new IdentifiedActor(ActorId::fromString('df516cba-fb13-4f45-8335-00252f1b87e2'));
        $actor2 = new IdentifiedActor(ActorId::fromString('00000000-0000-0000-0000-000000000000'));

        $this->assertNotSame($actor1->identifier(), $actor2->identifier());
    }

    #[Test]
    public function sameIdProducesSameIdentifier(): void
    {
        $id1 = ActorId::fromString('df516cba-fb13-4f45-8335-00252f1b87e2');
        $id2 = ActorId::fromString('df516cba-fb13-4f45-8335-00252f1b87e2');

        $actor1 = new IdentifiedActor($id1);
        $actor2 = new IdentifiedActor($id2);

        $this->assertSame($actor1->identifier(), $actor2->identifier());
    }

    #[Test]
    public function canBeExtended(): void
    {
        $id = ActorId::fromString('df516cba-fb13-4f45-8335-00252f1b87e2');
        $actor = new class($id) extends IdentifiedActor {};

        $this->assertInstanceOf(IdentifiedActor::class, $actor);
        $this->assertSame('df516cba-fb13-4f45-8335-00252f1b87e2', $actor->identifier());
    }
}
