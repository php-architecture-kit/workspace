<?php

declare(strict_types=1);

namespace Tests\PhpArchitecture\Graph\Unit\Events;

use PhpArchitecture\Graph\Edge\DirectedEdge;
use PhpArchitecture\Graph\Edge\DirectedEdgeInterface;
use PhpArchitecture\Graph\Edge\UndirectedEdgeInterface;
use PhpArchitecture\Graph\Events\EventDispatcher;
use PhpArchitecture\Graph\Events\Listener\OnEdgeAddedInterface;
use PhpArchitecture\Graph\Events\Listener\OnEdgeRemovedInterface;
use PhpArchitecture\Graph\Events\Listener\OnVertexAddedInterface;
use PhpArchitecture\Graph\Events\Listener\OnVertexRemovedInterface;
use PhpArchitecture\Graph\Vertex\Vertex;
use PhpArchitecture\Graph\Vertex\VertexInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class EventDispatcherSpyListener implements OnVertexAddedInterface, OnVertexRemovedInterface, OnEdgeAddedInterface, OnEdgeRemovedInterface
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

class EventDispatcherTest extends TestCase
{
    #[Test]
    public function dispatchMethodsCallRegisteredListeners(): void
    {
        $dispatcher = new EventDispatcher();
        $listener = new EventDispatcherSpyListener();

        $dispatcher->addOnVertexAddedListener($listener);
        $dispatcher->addOnVertexRemovedListener($listener);
        $dispatcher->addOnEdgeAddedListener($listener);
        $dispatcher->addOnEdgeRemovedListener($listener);

        $vertex = new Vertex();
        $edge = new DirectedEdge(new Vertex(), new Vertex());

        $dispatcher->dispatchVertexAdded($vertex);
        $dispatcher->dispatchVertexRemoved($vertex);
        $dispatcher->dispatchEdgeAdded($edge);
        $dispatcher->dispatchEdgeRemoved($edge);

        $this->assertSame(1, $listener->vertexAdded);
        $this->assertSame(1, $listener->vertexRemoved);
        $this->assertSame(1, $listener->edgeAdded);
        $this->assertSame(1, $listener->edgeRemoved);
    }

    #[Test]
    public function removeMethodsDetachListeners(): void
    {
        $dispatcher = new EventDispatcher();
        $listener = new EventDispatcherSpyListener();

        $dispatcher->addOnVertexAddedListener($listener);
        $dispatcher->addOnVertexRemovedListener($listener);
        $dispatcher->addOnEdgeAddedListener($listener);
        $dispatcher->addOnEdgeRemovedListener($listener);

        $dispatcher->removeOnVertexAddedListener($listener);
        $dispatcher->removeOnVertexRemovedListener($listener);
        $dispatcher->removeOnEdgeAddedListener($listener);
        $dispatcher->removeOnEdgeRemovedListener($listener);

        $vertex = new Vertex();
        $edge = new DirectedEdge(new Vertex(), new Vertex());

        $dispatcher->dispatchVertexAdded($vertex);
        $dispatcher->dispatchVertexRemoved($vertex);
        $dispatcher->dispatchEdgeAdded($edge);
        $dispatcher->dispatchEdgeRemoved($edge);

        $this->assertSame(0, $listener->vertexAdded);
        $this->assertSame(0, $listener->vertexRemoved);
        $this->assertSame(0, $listener->edgeAdded);
        $this->assertSame(0, $listener->edgeRemoved);
    }
}
