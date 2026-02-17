<?php

declare(strict_types=1);

namespace Tests\PhpArchitecture\Graph\Unit\Navigation\Traversal;

use PhpArchitecture\Graph\Edge\DirectedEdge;
use PhpArchitecture\Graph\Edge\DirectedEdgeInterface;
use PhpArchitecture\Graph\Edge\UndirectedEdgeInterface;
use PhpArchitecture\Graph\Graph;
use PhpArchitecture\Graph\Navigation\Traversal\EdgeTraversal;
use PhpArchitecture\Graph\Navigation\Traversal\EdgeVisitorInterface;
use PhpArchitecture\Graph\Navigation\Traversal\VisitAction;
use PhpArchitecture\Graph\Navigation\Traversal\VisitResult;
use PhpArchitecture\Graph\Vertex\Vertex;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class EdgeTraversalContinueVisitorA implements EdgeVisitorInterface
{
    /** @var list<string> */
    public array $visited = [];

    public function visit(DirectedEdgeInterface|UndirectedEdgeInterface $edge): VisitResult
    {
        $this->visited[] = $edge->id()->toString();

        return new VisitResult(VisitAction::Continue);
    }
}

class EdgeTraversalContinueVisitorB implements EdgeVisitorInterface
{
    /** @var list<string> */
    public array $visited = [];

    public function visit(DirectedEdgeInterface|UndirectedEdgeInterface $edge): VisitResult
    {
        $this->visited[] = $edge->id()->toString();

        return new VisitResult(VisitAction::Continue);
    }
}

class EdgeTraversalStopAtCurrentVisitor implements EdgeVisitorInterface
{
    /** @var list<string> */
    public array $visited = [];

    public function visit(DirectedEdgeInterface|UndirectedEdgeInterface $edge): VisitResult
    {
        $this->visited[] = $edge->id()->toString();

        return new VisitResult(VisitAction::StopAtCurrentEntity);
    }
}

class EdgeTraversalStopImmediatelyVisitor implements EdgeVisitorInterface
{
    /** @var list<string> */
    public array $visited = [];

    public function visit(DirectedEdgeInterface|UndirectedEdgeInterface $edge): VisitResult
    {
        $this->visited[] = $edge->id()->toString();

        return new VisitResult(VisitAction::StopImmediately);
    }
}

class EdgeTraversalTest extends TestCase
{
    #[Test]
    public function traverseVisitsFilteredEdgesAndCollectsResultsFromAllVisitors(): void
    {
        $graph = new Graph();
        $a = new Vertex();
        $b = new Vertex();
        $c = new Vertex();
        $d = new Vertex();

        $graph->vertexStore->addVertex($a);
        $graph->vertexStore->addVertex($b);
        $graph->vertexStore->addVertex($c);
        $graph->vertexStore->addVertex($d);

        $e1 = new DirectedEdge($a, $b, metadata: ['include' => true]);
        $e2 = new DirectedEdge($b, $c, metadata: ['include' => false]);
        $e3 = new DirectedEdge($c, $d, metadata: ['include' => true]);

        $graph->edgeStore->addEdge($e1);
        $graph->edgeStore->addEdge($e2);
        $graph->edgeStore->addEdge($e3);

        $visitorA = new EdgeTraversalContinueVisitorA();
        $visitorB = new EdgeTraversalContinueVisitorB();

        $traversal = new EdgeTraversal([$visitorA, $visitorB]);
        $result = $traversal->traverse(
            $graph,
            static fn(DirectedEdgeInterface|UndirectedEdgeInterface $edge): bool => ($edge->metadata['include'] ?? false) === true,
        );

        $this->assertSame([$e1->id->toString(), $e3->id->toString()], $visitorA->visited);
        $this->assertSame([$e1->id->toString(), $e3->id->toString()], $visitorB->visited);
        $this->assertTrue($result->has($e1->id, EdgeTraversalContinueVisitorA::class));
        $this->assertTrue($result->has($e3->id, EdgeTraversalContinueVisitorB::class));
        $this->assertFalse($result->has($e2->id, EdgeTraversalContinueVisitorA::class));
    }

    #[Test]
    public function traverseStopsAfterCurrentEdgeWhenVisitorReturnsStopAtCurrentEntity(): void
    {
        $graph = new Graph();
        $a = new Vertex();
        $b = new Vertex();
        $c = new Vertex();

        $graph->vertexStore->addVertex($a);
        $graph->vertexStore->addVertex($b);
        $graph->vertexStore->addVertex($c);

        $e1 = new DirectedEdge($a, $b);
        $e2 = new DirectedEdge($b, $c);

        $graph->edgeStore->addEdge($e1);
        $graph->edgeStore->addEdge($e2);

        $stopVisitor = new EdgeTraversalStopAtCurrentVisitor();
        $continueVisitor = new EdgeTraversalContinueVisitorB();

        $traversal = new EdgeTraversal([$stopVisitor, $continueVisitor]);
        $result = $traversal->traverse($graph);

        $this->assertSame([$e1->id->toString()], $stopVisitor->visited);
        $this->assertSame([$e1->id->toString()], $continueVisitor->visited);
        $this->assertTrue($result->has($e1->id, EdgeTraversalStopAtCurrentVisitor::class));
        $this->assertTrue($result->has($e1->id, EdgeTraversalContinueVisitorB::class));
        $this->assertFalse($result->has($e2->id, EdgeTraversalStopAtCurrentVisitor::class));
    }

    #[Test]
    public function traverseStopsImmediatelyWithoutCallingRemainingVisitors(): void
    {
        $graph = new Graph();
        $a = new Vertex();
        $b = new Vertex();
        $c = new Vertex();

        $graph->vertexStore->addVertex($a);
        $graph->vertexStore->addVertex($b);
        $graph->vertexStore->addVertex($c);

        $e1 = new DirectedEdge($a, $b);
        $e2 = new DirectedEdge($b, $c);

        $graph->edgeStore->addEdge($e1);
        $graph->edgeStore->addEdge($e2);

        $stopVisitor = new EdgeTraversalStopImmediatelyVisitor();
        $continueVisitor = new EdgeTraversalContinueVisitorA();

        $traversal = new EdgeTraversal([$stopVisitor, $continueVisitor]);
        $result = $traversal->traverse($graph);

        $this->assertSame([$e1->id->toString()], $stopVisitor->visited);
        $this->assertSame([], $continueVisitor->visited);
        $this->assertTrue($result->has($e1->id, EdgeTraversalStopImmediatelyVisitor::class));
        $this->assertFalse($result->has($e1->id, EdgeTraversalContinueVisitorA::class));
        $this->assertCount(1, $result->getAll());
    }
}
