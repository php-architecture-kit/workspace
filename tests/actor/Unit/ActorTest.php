<?php

declare(strict_types=1);

namespace Tests\PhpArchitecture\Actor\Unit;

use PhpArchitecture\Actor\Actor;
use PhpArchitecture\Actor\IdentifiedActor;
use PhpArchitecture\Actor\Identity\ActorId;
use PhpArchitecture\Actor\SystemActor;
use PhpArchitecture\Actor\UnknownActor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ActorTest extends TestCase
{
    #[Test]
    public function actorIsInterface(): void
    {
        $reflection = new \ReflectionClass(Actor::class);

        $this->assertTrue($reflection->isInterface());
    }

    #[Test]
    public function systemActorImplementsActor(): void
    {
        $actor = new SystemActor('cron');

        $this->assertInstanceOf(Actor::class, $actor);
    }

    #[Test]
    public function identifiedActorImplementsActor(): void
    {
        $id = ActorId::fromString('df516cba-fb13-4f45-8335-00252f1b87e2');
        $actor = new IdentifiedActor($id);

        $this->assertInstanceOf(Actor::class, $actor);
    }

    #[Test]
    public function unknownActorImplementsActor(): void
    {
        $actor = new UnknownActor();

        $this->assertInstanceOf(Actor::class, $actor);
    }
}
