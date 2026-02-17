<?php

declare(strict_types=1);

namespace Tests\PhpArchitecture\Graph\Functional;

use PhpArchitecture\Graph\Edge\DirectedEdge;
use PhpArchitecture\Graph\Edge\UndirectedEdge;
use PhpArchitecture\Graph\EdgeWeight\Config\WeightConfig;
use PhpArchitecture\Graph\EdgeWeight\EdgeWeights;
use PhpArchitecture\Graph\EdgeWeight\Weight;
use PhpArchitecture\Graph\Graph;
use PhpArchitecture\Graph\GraphConfig;
use PhpArchitecture\Graph\Vertex\Vertex;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class GraphBuildFunctionalTest extends TestCase
{
    #[Test]
    public function canBuildRealisticWeightedGraphAndQueryItsStructure(): void
    {
        $graph = new Graph(config: new GraphConfig(
            allowSelfLoop: false,
            allowMultiEdge: false,
            allowCyclicEdge: false,
            weightConfig: new WeightConfig([
                DirectedEdge::class => ['cost' => 1.0, 'latency' => 10.0],
                UndirectedEdge::class => ['cost' => 0.5],
            ]),
        ));

        $api = new Vertex(metadata: ['name' => 'api']);
        $service = new Vertex(metadata: ['name' => 'service']);
        $db = new Vertex(metadata: ['name' => 'db']);
        $cache = new Vertex(metadata: ['name' => 'cache']);

        $graph->vertexStore->addVertex($api);
        $graph->vertexStore->addVertex($service);
        $graph->vertexStore->addVertex($db);
        $graph->vertexStore->addVertex($cache);

        $apiToService = new DirectedEdge($api, $service);
        $serviceToDb = new DirectedEdge($service, $db);
        $apiToCache = new UndirectedEdge($api, $cache);

        $graph->edgeStore->addEdge(
            $apiToService,
            new EdgeWeights($apiToService->id, ['cost' => new Weight('cost', 3.0)]),
        );
        $graph->edgeStore->addEdge($serviceToDb);
        $graph->edgeStore->addEdge($apiToCache);

        $this->assertSame(4, $graph->vertexStore->count());
        $this->assertSame(3, $graph->edgeStore->count());
        $this->assertTrue($graph->edgeStore->areAdjacent($api->id, $service->id));
        $this->assertTrue($graph->edgeStore->areAdjacent($api->id, $cache->id));
        $this->assertFalse($graph->edgeStore->areAdjacent($cache->id, $db->id));
        $this->assertSame(2, $graph->edgeStore->degree($api->id));
        $this->assertCount(2, $graph->edgeStore->incidentEdges($api->id));

        $apiToServiceWeights = $graph->edgeStore->getEdgeWeights($apiToService->id);
        $serviceToDbWeights = $graph->edgeStore->getEdgeWeights($serviceToDb->id);
        $apiToCacheWeights = $graph->edgeStore->getEdgeWeights($apiToCache->id);

        $this->assertSame(3.0, $apiToServiceWeights->value('cost'));
        $this->assertSame(10.0, $apiToServiceWeights->value('latency'));
        $this->assertSame(1.0, $serviceToDbWeights->value('cost'));
        $this->assertSame(10.0, $serviceToDbWeights->value('latency'));
        $this->assertSame(0.5, $apiToCacheWeights->value('cost'));
    }

    #[Test]
    public function canContinueBuildingGraphAfterRemovingVertexWithIncidentEdges(): void
    {
        $graph = new Graph();

        $a = new Vertex(metadata: ['name' => 'A']);
        $b = new Vertex(metadata: ['name' => 'B']);
        $c = new Vertex(metadata: ['name' => 'C']);
        $d = new Vertex(metadata: ['name' => 'D']);

        $graph->vertexStore->addVertex($a);
        $graph->vertexStore->addVertex($b);
        $graph->vertexStore->addVertex($c);
        $graph->vertexStore->addVertex($d);

        $graph->edgeStore->addEdge(new DirectedEdge($a, $b));
        $graph->edgeStore->addEdge(new DirectedEdge($b, $c));
        $graph->edgeStore->addEdge(new DirectedEdge($c, $d));

        $this->assertSame(3, $graph->edgeStore->count());

        $graph->vertexStore->removeVertex($b->id);

        $this->assertSame(3, $graph->vertexStore->count());
        $this->assertSame(1, $graph->edgeStore->count());
        $this->assertTrue($graph->edgeStore->areAdjacent($c->id, $d->id));
        $this->assertFalse($graph->edgeStore->areAdjacent($a->id, $c->id));

        $newEdge = new DirectedEdge($a, $d);
        $graph->edgeStore->addEdge($newEdge);

        $this->assertSame(2, $graph->edgeStore->count());
        $this->assertCount(1, $graph->edgeStore->incidentEdges($a->id));
        $this->assertCount(2, $graph->edgeStore->incidentEdges($d->id));
    }
}
