<?php

declare(strict_types=1);

namespace Tests\PhpArchitecture\Graph\Unit\EdgeWeight;

use PhpArchitecture\Graph\Edge\DirectedEdge;
use PhpArchitecture\Graph\EdgeWeight\Config\WeightConfig;
use PhpArchitecture\Graph\EdgeWeight\EdgeWeightStore;
use PhpArchitecture\Graph\EdgeWeight\EdgeWeights;
use PhpArchitecture\Graph\EdgeWeight\Exception\EdgeWeightsAlreadyExistsException;
use PhpArchitecture\Graph\EdgeWeight\Exception\EdgeWeightsNotFoundException;
use PhpArchitecture\Graph\EdgeWeight\Weight;
use PhpArchitecture\Graph\Vertex\Vertex;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class EdgeWeightStoreTest extends TestCase
{
    #[Test]
    public function addEdgeWeightsStoresWeightsAndFillsDefaults(): void
    {
        $store = new EdgeWeightStore(new WeightConfig([
            DirectedEdge::class => ['cost' => 10.0, 'time' => 5.0],
        ]));

        $edge = new DirectedEdge(new Vertex(), new Vertex());
        $input = new EdgeWeights(
            $edge->id,
            ['cost' => new Weight('cost', 12.0)],
        );

        $store->addEdgeWeights($edge, $input);
        $result = $store->edgeWeights($edge->id);

        $this->assertSame(12.0, $result->value('cost'));
        $this->assertSame(5.0, $result->value('time'));
        $this->assertTrue($result->edgeId->equals($edge->id));
    }

    #[Test]
    public function addEdgeWeightsThrowsWhenWeightsAlreadyExistForEdge(): void
    {
        $store = new EdgeWeightStore(new WeightConfig([
            DirectedEdge::class => ['cost' => 10.0],
        ]));

        $edge = new DirectedEdge(new Vertex(), new Vertex());
        $weights = new EdgeWeights($edge->id, []);

        $store->addEdgeWeights($edge, $weights);

        $this->expectException(EdgeWeightsAlreadyExistsException::class);

        $store->addEdgeWeights($edge, $weights);
    }

    #[Test]
    public function edgeWeightsThrowsWhenMissing(): void
    {
        $store = new EdgeWeightStore(new WeightConfig());

        $this->expectException(EdgeWeightsNotFoundException::class);

        $store->edgeWeights((new DirectedEdge(new Vertex(), new Vertex()))->id);
    }

    #[Test]
    public function removeEdgeWeightsRemovesExistingEntry(): void
    {
        $store = new EdgeWeightStore(new WeightConfig([
            DirectedEdge::class => ['cost' => 1.0],
        ]));
        $edge = new DirectedEdge(new Vertex(), new Vertex());
        $store->addEdgeWeights($edge, new EdgeWeights($edge->id, []));

        $store->removeEdgeWeights($edge->id);

        $this->expectException(EdgeWeightsNotFoundException::class);

        $store->edgeWeights($edge->id);
    }

    #[Test]
    public function populateEdgeDefaultWeightsAddsDefaultsForMissingEdges(): void
    {
        $store = new EdgeWeightStore(new WeightConfig([
            DirectedEdge::class => ['cost' => 2.5],
        ]));
        $edge = new DirectedEdge(new Vertex(), new Vertex());

        $store->populateEdgeDefaultWeights([$edge->id->toString() => $edge]);

        $weights = $store->edgeWeights($edge->id);

        $this->assertSame(2.5, $weights->value('cost'));
        $this->assertTrue($weights->edgeId->equals($edge->id));
    }

    #[Test]
    public function populateEdgeDefaultWeightsDoesNotOverrideExistingValues(): void
    {
        $store = new EdgeWeightStore(new WeightConfig([
            DirectedEdge::class => ['cost' => 10.0, 'time' => 3.0],
        ]));

        $edge = new DirectedEdge(new Vertex(), new Vertex());
        $store->addEdgeWeights(
            $edge,
            new EdgeWeights($edge->id, ['cost' => new Weight('cost', 20.0)]),
        );

        $store->populateEdgeDefaultWeights([$edge->id->toString() => $edge]);

        $weights = $store->edgeWeights($edge->id);

        $this->assertSame(20.0, $weights->value('cost'));
        $this->assertSame(3.0, $weights->value('time'));
    }
}
