<?php

declare(strict_types=1);

namespace Tests\PhpArchitecture\Actor\Unit;

use PhpArchitecture\Actor\Actor;
use PhpArchitecture\Actor\SystemActor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SystemActorTest extends TestCase
{
    #[Test]
    public function implementsActorInterface(): void
    {
        $actor = new SystemActor('scheduler');

        $this->assertInstanceOf(Actor::class, $actor);
    }

    #[Test]
    public function identifierReturnsName(): void
    {
        $actor = new SystemActor('cron-job');

        $this->assertSame('cron-job', $actor->identifier());
    }

    #[Test]
    public function differentNamesProduceDifferentIdentifiers(): void
    {
        $actor1 = new SystemActor('scheduler');
        $actor2 = new SystemActor('worker');

        $this->assertNotSame($actor1->identifier(), $actor2->identifier());
    }

    #[Test]
    public function sameNameProducesSameIdentifier(): void
    {
        $actor1 = new SystemActor('cron');
        $actor2 = new SystemActor('cron');

        $this->assertSame($actor1->identifier(), $actor2->identifier());
    }

    #[Test]
    public function canBeExtended(): void
    {
        $actor = new class('custom') extends SystemActor {};

        $this->assertInstanceOf(SystemActor::class, $actor);
        $this->assertSame('custom', $actor->identifier());
    }
}
