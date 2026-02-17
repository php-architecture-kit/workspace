<?php

declare(strict_types=1);

namespace Tests\PhpArchitecture\Graph\Unit\Vertex;

use PhpArchitecture\Graph\Vertex\Identity\VertexId;
use PhpArchitecture\Graph\Vertex\Vertex;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class VertexTest extends TestCase
{
    #[Test]
    public function constructorGeneratesIdWhenNotProvided(): void
    {
        $vertex = new Vertex();

        $this->assertInstanceOf(VertexId::class, $vertex->id);
        $this->assertTrue($vertex->id->validate());
    }

    #[Test]
    public function constructorUsesProvidedIdAndMetadata(): void
    {
        $id = VertexId::fromString('df516cba-fb13-4f45-8335-00252f1b87e2');
        $vertex = new Vertex($id, ['role' => 'root']);

        $this->assertSame($id, $vertex->id);
        $this->assertSame(['role' => 'root'], $vertex->metadata);
    }
}
