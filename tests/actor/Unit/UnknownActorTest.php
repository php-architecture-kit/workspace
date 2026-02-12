<?php

declare(strict_types=1);

namespace Tests\PhpArchitecture\Actor\Unit;

use PhpArchitecture\Actor\Actor;
use PhpArchitecture\Actor\UnknownActor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class UnknownActorTest extends TestCase
{
    #[Test]
    public function implementsActorInterface(): void
    {
        $actor = new UnknownActor();

        $this->assertInstanceOf(Actor::class, $actor);
    }

    #[Test]
    public function identifierReturnsUnknown(): void
    {
        $actor = new UnknownActor();

        $this->assertSame('unknown', $actor->identifier());
    }

    #[Test]
    public function identifierConstantMatchesReturnValue(): void
    {
        $actor = new UnknownActor();

        $this->assertSame(UnknownActor::IDENTIFIER, $actor->identifier());
    }

    #[Test]
    public function allInstancesHaveSameIdentifier(): void
    {
        $actor1 = new UnknownActor();
        $actor2 = new UnknownActor();

        $this->assertSame($actor1->identifier(), $actor2->identifier());
    }
}
