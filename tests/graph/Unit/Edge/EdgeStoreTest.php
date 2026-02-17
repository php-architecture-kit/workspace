<?php

declare(strict_types=1);

namespace Tests\PhpArchitecture\Graph\Unit\Edge;

use PhpArchitecture\Graph\Edge\DirectedEdge;
use PhpArchitecture\Graph\Edge\Identity\EdgeId;
use PhpArchitecture\Graph\Edge\Exception\EdgeAlreadyExistsException;
use PhpArchitecture\Graph\Edge\Exception\EdgeNotFoundException;
use PhpArchitecture\Graph\Edge\Exception\MissingEdgeWeightStoreException;
use PhpArchitecture\Graph\Edge\UndirectedEdge;
use PhpArchitecture\Graph\EdgeWeight\Config\WeightConfig;
use PhpArchitecture\Graph\EdgeWeight\EdgeWeights;
use PhpArchitecture\Graph\EdgeWeight\Weight;
use PhpArchitecture\Graph\Graph;
use PhpArchitecture\Graph\GraphConfig;
use PhpArchitecture\Graph\Vertex\Exception\VertexNotInGraphException;
use PhpArchitecture\Graph\Vertex\Vertex;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class EdgeStoreTest extends TestCase
{
    #[Test]
    public function addEdgeStoresEdgeAndIncreasesCount(): void
    {
        $graph = new Graph();
        $u = new Vertex();
        $v = new Vertex();
        $graph->vertexStore->addVertex($u);
        $graph->vertexStore->addVertex($v);
        $edge = new DirectedEdge($u, $v);

        $graph->edgeStore->addEdge($edge);

        $this->assertSame(1, $graph->edgeStore->count());
        $this->assertTrue($graph->edgeStore->hasEdge($edge->id));
        $this->assertSame($edge, $graph->edgeStore->getEdge($edge->id));
    }

    #[Test]
    public function addEdgeThrowsWhenVertexIsNotInGraph(): void
    {
        $graph = new Graph();
        $u = new Vertex();
        $v = new Vertex();
        $graph->vertexStore->addVertex($u);

        $this->expectException(VertexNotInGraphException::class);

        $graph->edgeStore->addEdge(new DirectedEdge($u, $v));
    }

    #[Test]
    public function addEdgeThrowsWhenEdgeAlreadyExists(): void
    {
        $graph = new Graph();
        $u = new Vertex();
        $v = new Vertex();
        $graph->vertexStore->addVertex($u);
        $graph->vertexStore->addVertex($v);

        $edge = new DirectedEdge($u, $v);
        $graph->edgeStore->addEdge($edge);

        $this->expectException(EdgeAlreadyExistsException::class);

        $graph->edgeStore->addEdge(new DirectedEdge($u, $v, $edge->id));
    }

    #[Test]
    public function getEdgeThrowsWhenMissingAndRequested(): void
    {
        $graph = new Graph();
        $edgeId = EdgeId::new();

        $this->expectException(EdgeNotFoundException::class);

        $graph->edgeStore->getEdge($edgeId, true);
    }

    #[Test]
    public function removeEdgeDeletesEdge(): void
    {
        $graph = new Graph();
        $u = new Vertex();
        $v = new Vertex();
        $graph->vertexStore->addVertex($u);
        $graph->vertexStore->addVertex($v);
        $edge = new DirectedEdge($u, $v);
        $graph->edgeStore->addEdge($edge);

        $graph->edgeStore->removeEdge($edge->id);

        $this->assertSame(0, $graph->edgeStore->count());
        $this->assertFalse($graph->edgeStore->hasEdge($edge->id));
    }

    #[Test]
    public function removeEdgeThrowsWhenMissing(): void
    {
        $graph = new Graph();
        $edgeId = EdgeId::new();

        $this->expectException(EdgeNotFoundException::class);

        $graph->edgeStore->removeEdge($edgeId);
    }

    #[Test]
    public function incidentAndAdjacencyMethodsWork(): void
    {
        $graph = new Graph();
        $u = new Vertex();
        $v = new Vertex();
        $w = new Vertex();
        $graph->vertexStore->addVertex($u);
        $graph->vertexStore->addVertex($v);
        $graph->vertexStore->addVertex($w);

        $e1 = new DirectedEdge($u, $v);
        $e2 = new UndirectedEdge($u, $w);
        $graph->edgeStore->addEdge($e1);
        $graph->edgeStore->addEdge($e2);

        $this->assertTrue($graph->edgeStore->areAdjacent($u->id, $v->id));
        $this->assertTrue($graph->edgeStore->areAdjacent($u->id, $w->id));
        $this->assertFalse($graph->edgeStore->areAdjacent($v->id, $w->id));
        $this->assertSame(2, $graph->edgeStore->degree($u->id));
        $this->assertCount(2, $graph->edgeStore->incidentEdges($u->id));
    }

    #[Test]
    public function getEdgeWeightsThrowsWhenWeightStoreIsMissing(): void
    {
        $graph = new Graph();
        $u = new Vertex();
        $v = new Vertex();
        $graph->vertexStore->addVertex($u);
        $graph->vertexStore->addVertex($v);
        $edge = new DirectedEdge($u, $v);
        $graph->edgeStore->addEdge($edge);

        $this->expectException(MissingEdgeWeightStoreException::class);

        $graph->edgeStore->getEdgeWeights($edge->id);
    }

    #[Test]
    public function addEdgeStoresProvidedAndDefaultWeights(): void
    {
        $config = new GraphConfig(weightConfig: new WeightConfig([
            DirectedEdge::class => ['cost' => 10.0, 'time' => 5.0],
        ]));

        $graph = new Graph(config: $config);
        $u = new Vertex();
        $v = new Vertex();
        $graph->vertexStore->addVertex($u);
        $graph->vertexStore->addVertex($v);

        $edge = new DirectedEdge($u, $v);
        $weights = new EdgeWeights($edge->id, ['cost' => new Weight('cost', 15.0)]);

        $graph->edgeStore->addEdge($edge, $weights);

        $result = $graph->edgeStore->getEdgeWeights($edge->id);
        $this->assertSame(15.0, $result->value('cost'));
        $this->assertSame(5.0, $result->value('time'));
    }
}
