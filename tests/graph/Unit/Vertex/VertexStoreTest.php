<?php

declare(strict_types=1);

namespace Tests\PhpArchitecture\Graph\Unit\Vertex;

use PhpArchitecture\Graph\Edge\DirectedEdge;
use PhpArchitecture\Graph\Graph;
use PhpArchitecture\Graph\Vertex\Exception\VertexAlreadyExistsException;
use PhpArchitecture\Graph\Vertex\Exception\VertexNotFoundException;
use PhpArchitecture\Graph\Vertex\Vertex;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class VertexStoreTest extends TestCase
{
    #[Test]
    public function addVertexStoresVertexAndIncreasesCount(): void
    {
        $graph = new Graph();
        $vertex = new Vertex();

        $graph->vertexStore->addVertex($vertex);

        $this->assertSame(1, $graph->vertexStore->count());
        $this->assertSame($vertex, $graph->vertexStore->getVertex($vertex->id));
        $this->assertTrue($graph->vertexStore->hasVertex($vertex->id));
    }

    #[Test]
    public function addVertexThrowsExceptionForDuplicateId(): void
    {
        $graph = new Graph();
        $vertex = new Vertex();

        $graph->vertexStore->addVertex($vertex);

        $this->expectException(VertexAlreadyExistsException::class);

        $graph->vertexStore->addVertex(new Vertex($vertex->id));
    }

    #[Test]
    public function getVertexThrowsExceptionWhenRequestedAndMissing(): void
    {
        $graph = new Graph();
        $vertex = new Vertex();

        $this->expectException(VertexNotFoundException::class);

        $graph->vertexStore->getVertex($vertex->id, true);
    }

    #[Test]
    public function removeVertexDeletesVertexAndIncidentEdges(): void
    {
        $graph = new Graph();
        $v1 = new Vertex();
        $v2 = new Vertex();

        $graph->vertexStore->addVertex($v1);
        $graph->vertexStore->addVertex($v2);
        $edge = new DirectedEdge($v1, $v2);
        $graph->edgeStore->addEdge($edge);

        $graph->vertexStore->removeVertex($v1->id);

        $this->assertSame(1, $graph->vertexStore->count());
        $this->assertFalse($graph->vertexStore->hasVertex($v1->id));
        $this->assertSame(0, $graph->edgeStore->count());
    }

    #[Test]
    public function removeVertexThrowsExceptionWhenMissing(): void
    {
        $graph = new Graph();
        $vertex = new Vertex();

        $this->expectException(VertexNotFoundException::class);

        $graph->vertexStore->removeVertex($vertex->id);
    }

    #[Test]
    public function getVerticesAcceptsFilter(): void
    {
        $graph = new Graph();
        $root = new Vertex(metadata: ['type' => 'root']);
        $leaf = new Vertex(metadata: ['type' => 'leaf']);

        $graph->vertexStore->addVertex($root);
        $graph->vertexStore->addVertex($leaf);

        $result = $graph->vertexStore->getVertices(
            static fn(Vertex $vertex): bool => ($vertex->metadata['type'] ?? null) === 'root',
        );

        $this->assertCount(1, $result);
        $this->assertSame($root, array_values($result)[0]);
    }
}
