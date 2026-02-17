<?php

declare(strict_types=1);

namespace Tests\PhpArchitecture\Graph\Unit;

use PhpArchitecture\Graph\Edge\DirectedEdge;
use PhpArchitecture\Graph\Edge\EdgeStore;
use PhpArchitecture\Graph\Edge\Validator\CyclicEdgeValidator;
use PhpArchitecture\Graph\Edge\Validator\MultiEdgeValidator;
use PhpArchitecture\Graph\Edge\Validator\SelfLoopValidator;
use PhpArchitecture\Graph\EdgeWeight\Config\WeightConfig;
use PhpArchitecture\Graph\Graph;
use PhpArchitecture\Graph\GraphConfig;
use PhpArchitecture\Graph\Index\IncidenceIndex;
use PhpArchitecture\Graph\Vertex\Vertex;
use PhpArchitecture\Graph\Vertex\VertexStore;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class GraphTest extends TestCase
{
    #[Test]
    public function constructorWiresDefaultCollaborators(): void
    {
        $graph = new Graph();

        $this->assertInstanceOf(VertexStore::class, $graph->vertexStore);
        $this->assertInstanceOf(EdgeStore::class, $graph->edgeStore);
        $this->assertNotNull($graph->indexRegistry->index(IncidenceIndex::class));
    }

    #[Test]
    public function constructorUsesProvidedStoresAndSetsGraphOnThem(): void
    {
        $vertexStore = new class extends VertexStore {
            public function graphPublic(): Graph
            {
                return $this->graph();
            }
        };

        $edgeStore = new class extends EdgeStore {
            public function graphPublic(): Graph
            {
                return $this->graph();
            }
        };

        $graph = new Graph(vertexStore: $vertexStore, edgeStore: $edgeStore);

        $this->assertSame($vertexStore, $graph->vertexStore);
        $this->assertSame($edgeStore, $graph->edgeStore);
        $this->assertSame($graph, $vertexStore->graphPublic());
        $this->assertSame($graph, $edgeStore->graphPublic());
    }

    #[Test]
    public function constructorRebuildsEdgeStoreWithWeightsWhenConfigRequiresWeightsAndInjectedStoreHasNoWeightStore(): void
    {
        $v1 = new Vertex();
        $v2 = new Vertex();

        $initialGraph = new Graph();
        $initialGraph->vertexStore->addVertex($v1);
        $initialGraph->vertexStore->addVertex($v2);
        $edge = new DirectedEdge($v1, $v2);
        $initialGraph->edgeStore->addEdge($edge);

        $weightConfig = new WeightConfig([DirectedEdge::class => ['cost' => 3.0]]);
        $graph = new Graph(
            config: new GraphConfig(weightConfig: $weightConfig),
            edgeStore: $initialGraph->edgeStore,
        );

        $this->assertTrue($graph->edgeStore->hasWeightStore());
        $this->assertSame(3.0, $graph->edgeStore->getEdgeWeights($edge->id)->value('cost'));
    }

    #[Test]
    public function validatorChainContainsValidatorsBasedOnConfigFlags(): void
    {
        $graph = new Graph(new GraphConfig(
            allowSelfLoop: false,
            allowMultiEdge: false,
            allowCyclicEdge: false,
        ));

        $validatorChain = new \ReflectionClass($graph->edgeValidator);
        $property = $validatorChain->getProperty('validators');
        $property->setAccessible(true);

        /** @var array<int,object> $validators */
        $validators = $property->getValue($graph->edgeValidator);

        $this->assertCount(3, $validators);
        $this->assertInstanceOf(SelfLoopValidator::class, $validators[0]);
        $this->assertInstanceOf(MultiEdgeValidator::class, $validators[1]);
        $this->assertInstanceOf(CyclicEdgeValidator::class, $validators[2]);
    }
}
