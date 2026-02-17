<?php

declare(strict_types=1);

namespace Tests\PhpArchitecture\Graph\Unit\Edge\Identity;

use PhpArchitecture\Graph\Edge\Identity\EdgeId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class EdgeIdTest extends TestCase
{
    #[Test]
    public function fromStringCreatesEdgeId(): void
    {
        $id = EdgeId::fromString('df516cba-fb13-4f45-8335-00252f1b87e2');

        $this->assertInstanceOf(EdgeId::class, $id);
        $this->assertSame('df516cba-fb13-4f45-8335-00252f1b87e2', $id->toString());
    }

    #[Test]
    public function newCreatesValidEdgeId(): void
    {
        $id = EdgeId::new();

        $this->assertInstanceOf(EdgeId::class, $id);
        $this->assertTrue($id->validate());
    }
}
