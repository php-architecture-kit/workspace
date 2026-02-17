<?php

declare(strict_types=1);

namespace Tests\PhpArchitecture\Graph\Unit\Navigation;

use PhpArchitecture\Graph\Edge\DirectedEdge;
use PhpArchitecture\Graph\Edge\DirectedEdgeInterface;
use PhpArchitecture\Graph\Edge\UndirectedEdgeInterface;
use PhpArchitecture\Graph\Graph;
use PhpArchitecture\Graph\GraphNavigator;
use PhpArchitecture\Graph\Navigation\Traversal\EdgeVisitorInterface;
use PhpArchitecture\Graph\Navigation\Traversal\VertexVisitorInterface;
use PhpArchitecture\Graph\Navigation\Traversal\VisitAction;
use PhpArchitecture\Graph\Navigation\Traversal\VisitResult;
use PhpArchitecture\Graph\Vertex\Vertex;
use PhpArchitecture\Graph\Vertex\VertexInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class GraphNavigatorVertexVisitor implements VertexVisitorInterface
{
    /** @var list<string> */
    public array $visited = [];

    public function visit(VertexInterface $vertex): VisitResult
    {
        $this->visited[] = $vertex->id()->toString();

        return new VisitResult(VisitAction::Continue);
    }
}

class GraphNavigatorEdgeVisitor implements EdgeVisitorInterface
{
    /** @var list<string> */
    public array $visited = [];

    public function visit(DirectedEdgeInterface|UndirectedEdgeInterface $edge): VisitResult
    {
        $this->visited[] = $edge->id()->toString();

        return new VisitResult(VisitAction::Continue);
    }
}

class GraphNavigatorTest extends TestCase
{
    #[Test]
    public function constructorStoresGraphReference(): void
    {
        $graph = new Graph();

        $navigator = new GraphNavigator($graph);

        $this->assertSame($graph, $navigator->graph);
    }

    #[Test]
    public function traverseVerticesDelegatesToVertexTraversalAndReturnsResult(): void
    {
        $graph = new Graph();
        $v1 = new Vertex(metadata: ['include' => true]);
        $v2 = new Vertex(metadata: ['include' => false]);

        $graph->vertexStore->addVertex($v1);
        $graph->vertexStore->addVertex($v2);

        $visitor = new GraphNavigatorVertexVisitor();
        $navigator = new GraphNavigator($graph);

        $result = $navigator->traverseVertices(
            [$visitor],
            static fn(Vertex $vertex): bool => ($vertex->metadata['include'] ?? false) === true,
        );

        $this->assertSame([$v1->id->toString()], $visitor->visited);
        $this->assertTrue($result->has($v1->id, GraphNavigatorVertexVisitor::class));
        $this->assertFalse($result->has($v2->id, GraphNavigatorVertexVisitor::class));
    }

    #[Test]
    public function traverseEdgesDelegatesToEdgeTraversalAndReturnsResult(): void
    {
        $graph = new Graph();
        $a = new Vertex();
        $b = new Vertex();
        $c = new Vertex();

        $graph->vertexStore->addVertex($a);
        $graph->vertexStore->addVertex($b);
        $graph->vertexStore->addVertex($c);

        $e1 = new DirectedEdge($a, $b, metadata: ['include' => true]);
        $e2 = new DirectedEdge($b, $c, metadata: ['include' => false]);

        $graph->edgeStore->addEdge($e1);
        $graph->edgeStore->addEdge($e2);

        $visitor = new GraphNavigatorEdgeVisitor();
        $navigator = new GraphNavigator($graph);

        $result = $navigator->traverseEdges(
            [$visitor],
            static fn(DirectedEdgeInterface|UndirectedEdgeInterface $edge): bool => ($edge->metadata['include'] ?? false) === true,
        );

        $this->assertSame([$e1->id->toString()], $visitor->visited);
        $this->assertTrue($result->has($e1->id, GraphNavigatorEdgeVisitor::class));
        $this->assertFalse($result->has($e2->id, GraphNavigatorEdgeVisitor::class));
    }
}
