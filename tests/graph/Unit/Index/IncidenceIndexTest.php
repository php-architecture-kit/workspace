<?php

declare(strict_types=1);

namespace Tests\PhpArchitecture\Graph\Unit\Index;

use PhpArchitecture\Graph\Edge\DirectedEdge;
use PhpArchitecture\Graph\Index\IncidenceIndex;
use PhpArchitecture\Graph\Vertex\Vertex;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class IncidenceIndexTest extends TestCase
{
    #[Test]
    public function onEdgeAddedIndexesEdgeForBothVertices(): void
    {
        $u = new Vertex();
        $v = new Vertex();
        $edge = new DirectedEdge($u, $v);

        $index = new IncidenceIndex();
        $index->onEdgeAdded($edge);

        $this->assertCount(1, $index->edgesFor($u->id));
        $this->assertCount(1, $index->edgesFor($v->id));
        $this->assertSame(1, $index->degree($u->id));
        $this->assertSame(1, $index->degree($v->id));
    }

    #[Test]
    public function onEdgeRemovedRemovesEdgeFromBothVertices(): void
    {
        $u = new Vertex();
        $v = new Vertex();
        $edge = new DirectedEdge($u, $v);

        $index = new IncidenceIndex();
        $index->onEdgeAdded($edge);
        $index->onEdgeRemoved($edge);

        $this->assertSame([], $index->edgesFor($u->id));
        $this->assertSame([], $index->edgesFor($v->id));
        $this->assertSame(0, $index->degree($u->id));
    }
}
