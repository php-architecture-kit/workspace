<?php

declare(strict_types=1);

namespace Tests\PhpArchitecture\Graph\Unit\Vertex\Identity;

use PhpArchitecture\Graph\Vertex\Identity\VertexId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class VertexIdTest extends TestCase
{
    #[Test]
    public function fromStringCreatesVertexId(): void
    {
        $id = VertexId::fromString('df516cba-fb13-4f45-8335-00252f1b87e2');

        $this->assertInstanceOf(VertexId::class, $id);
        $this->assertSame('df516cba-fb13-4f45-8335-00252f1b87e2', $id->toString());
    }

    #[Test]
    public function newCreatesValidVertexId(): void
    {
        $id = VertexId::new();

        $this->assertInstanceOf(VertexId::class, $id);
        $this->assertTrue($id->validate());
    }
}
