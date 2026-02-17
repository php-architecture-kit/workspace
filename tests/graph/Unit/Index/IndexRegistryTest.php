<?php

declare(strict_types=1);

namespace Tests\PhpArchitecture\Graph\Unit\Index;

use PhpArchitecture\Graph\Edge\DirectedEdge;
use PhpArchitecture\Graph\Edge\DirectedEdgeInterface;
use PhpArchitecture\Graph\Edge\UndirectedEdgeInterface;
use PhpArchitecture\Graph\Events\Listener\OnEdgeAddedInterface;
use PhpArchitecture\Graph\Events\Listener\OnEdgeRemovedInterface;
use PhpArchitecture\Graph\Events\Listener\OnVertexAddedInterface;
use PhpArchitecture\Graph\Events\Listener\OnVertexRemovedInterface;
use PhpArchitecture\Graph\Index\IndexRegistry;
use PhpArchitecture\Graph\Vertex\Vertex;
use PhpArchitecture\Graph\Vertex\VertexInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class IndexRegistrySpy implements OnVertexAddedInterface, OnVertexRemovedInterface, OnEdgeAddedInterface, OnEdgeRemovedInterface
{
    public int $vertexAdded = 0;
    public int $vertexRemoved = 0;
    public int $edgeAdded = 0;
    public int $edgeRemoved = 0;

    public function onVertexAdded(VertexInterface $vertex): void
    {
        $this->vertexAdded++;
    }

    public function onVertexRemoved(VertexInterface $vertex): void
    {
        $this->vertexRemoved++;
    }

    public function onEdgeAdded(DirectedEdgeInterface|UndirectedEdgeInterface $edge): void
    {
        $this->edgeAdded++;
    }

    public function onEdgeRemoved(DirectedEdgeInterface|UndirectedEdgeInterface $edge): void
    {
        $this->edgeRemoved++;
    }
}

class IndexRegistryTest extends TestCase
{
    #[Test]
    public function registerAndIndexReturnRegisteredIndex(): void
    {
        $registry = new IndexRegistry();
        $index = new IndexRegistrySpy();

        $registry->register($index);

        $this->assertSame($index, $registry->index(IndexRegistrySpy::class));
        $this->assertArrayHasKey(IndexRegistrySpy::class, $registry->all());
    }

    #[Test]
    public function unregisterRemovesRegisteredIndex(): void
    {
        $registry = new IndexRegistry();
        $index = new IndexRegistrySpy();

        $registry->register($index);
        $registry->unregister($index);

        $this->assertNull($registry->index(IndexRegistrySpy::class));
        $this->assertSame([], $registry->all());
    }

    #[Test]
    public function eventMethodsForwardCallsToRegisteredListeners(): void
    {
        $registry = new IndexRegistry();
        $index = new IndexRegistrySpy();
        $registry->register($index);

        $u = new Vertex();
        $v = new Vertex();
        $edge = new DirectedEdge($u, $v);

        $registry->onVertexAdded($u);
        $registry->onVertexRemoved($u);
        $registry->onEdgeAdded($edge);
        $registry->onEdgeRemoved($edge);

        $this->assertSame(1, $index->vertexAdded);
        $this->assertSame(1, $index->vertexRemoved);
        $this->assertSame(1, $index->edgeAdded);
        $this->assertSame(1, $index->edgeRemoved);
    }
}
