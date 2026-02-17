<?php

declare(strict_types=1);

namespace Tests\PhpArchitecture\Graph\Unit\Index;

use PhpArchitecture\Graph\Edge\DirectedEdge;
use PhpArchitecture\Graph\Events\EventDispatcher;
use PhpArchitecture\Graph\Index\IndexNotifier;
use PhpArchitecture\Graph\Vertex\Vertex;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class IndexNotifierTest extends TestCase
{
    #[Test]
    public function notifyVertexAddedDispatchesEvent(): void
    {
        $dispatcher = $this->createMock(EventDispatcher::class);
        $notifier = new IndexNotifier($dispatcher);
        $vertex = new Vertex();

        $dispatcher->expects($this->once())
            ->method('dispatchVertexAdded')
            ->with($vertex);

        $notifier->notifyVertexAdded($vertex);
    }

    #[Test]
    public function notifyVertexRemovedDispatchesEvent(): void
    {
        $dispatcher = $this->createMock(EventDispatcher::class);
        $notifier = new IndexNotifier($dispatcher);
        $vertex = new Vertex();

        $dispatcher->expects($this->once())
            ->method('dispatchVertexRemoved')
            ->with($vertex);

        $notifier->notifyVertexRemoved($vertex);
    }

    #[Test]
    public function notifyEdgeAddedAndRemovedDispatchEvents(): void
    {
        $dispatcher = $this->createMock(EventDispatcher::class);
        $notifier = new IndexNotifier($dispatcher);
        $edge = new DirectedEdge(new Vertex(), new Vertex());

        $dispatcher->expects($this->once())
            ->method('dispatchEdgeAdded')
            ->with($edge);
        $dispatcher->expects($this->once())
            ->method('dispatchEdgeRemoved')
            ->with($edge);

        $notifier->notifyEdgeAdded($edge);
        $notifier->notifyEdgeRemoved($edge);
    }
}
