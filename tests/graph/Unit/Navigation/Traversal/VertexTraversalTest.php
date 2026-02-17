<?php

declare(strict_types=1);

namespace Tests\PhpArchitecture\Graph\Unit\Navigation\Traversal;

use PhpArchitecture\Graph\Graph;
use PhpArchitecture\Graph\Navigation\Traversal\VertexTraversal;
use PhpArchitecture\Graph\Navigation\Traversal\VertexVisitorInterface;
use PhpArchitecture\Graph\Navigation\Traversal\VisitAction;
use PhpArchitecture\Graph\Navigation\Traversal\VisitResult;
use PhpArchitecture\Graph\Vertex\Vertex;
use PhpArchitecture\Graph\Vertex\VertexInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class VertexTraversalContinueVisitorA implements VertexVisitorInterface
{
    /** @var list<string> */
    public array $visited = [];

    public function visit(VertexInterface $vertex): VisitResult
    {
        $this->visited[] = $vertex->id()->toString();

        return new VisitResult(VisitAction::Continue);
    }
}

class VertexTraversalContinueVisitorB implements VertexVisitorInterface
{
    /** @var list<string> */
    public array $visited = [];

    public function visit(VertexInterface $vertex): VisitResult
    {
        $this->visited[] = $vertex->id()->toString();

        return new VisitResult(VisitAction::Continue);
    }
}

class VertexTraversalStopAtCurrentVisitor implements VertexVisitorInterface
{
    /** @var list<string> */
    public array $visited = [];

    public function visit(VertexInterface $vertex): VisitResult
    {
        $this->visited[] = $vertex->id()->toString();

        return new VisitResult(VisitAction::StopAtCurrentEntity);
    }
}

class VertexTraversalStopImmediatelyVisitor implements VertexVisitorInterface
{
    /** @var list<string> */
    public array $visited = [];

    public function visit(VertexInterface $vertex): VisitResult
    {
        $this->visited[] = $vertex->id()->toString();

        return new VisitResult(VisitAction::StopImmediately);
    }
}

class VertexTraversalTest extends TestCase
{
    #[Test]
    public function traverseVisitsFilteredVerticesAndCollectsResultsFromAllVisitors(): void
    {
        $graph = new Graph();
        $v1 = new Vertex(metadata: ['include' => true]);
        $v2 = new Vertex(metadata: ['include' => false]);
        $v3 = new Vertex(metadata: ['include' => true]);

        $graph->vertexStore->addVertex($v1);
        $graph->vertexStore->addVertex($v2);
        $graph->vertexStore->addVertex($v3);

        $visitorA = new VertexTraversalContinueVisitorA();
        $visitorB = new VertexTraversalContinueVisitorB();

        $traversal = new VertexTraversal([$visitorA, $visitorB]);
        $result = $traversal->traverse(
            $graph,
            static fn(Vertex $vertex): bool => ($vertex->metadata['include'] ?? false) === true,
        );

        $this->assertSame([$v1->id->toString(), $v3->id->toString()], $visitorA->visited);
        $this->assertSame([$v1->id->toString(), $v3->id->toString()], $visitorB->visited);
        $this->assertTrue($result->has($v1->id, VertexTraversalContinueVisitorA::class));
        $this->assertTrue($result->has($v3->id, VertexTraversalContinueVisitorB::class));
        $this->assertFalse($result->has($v2->id, VertexTraversalContinueVisitorA::class));
    }

    #[Test]
    public function traverseStopsAfterCurrentVertexWhenVisitorReturnsStopAtCurrentEntity(): void
    {
        $graph = new Graph();
        $v1 = new Vertex();
        $v2 = new Vertex();
        $v3 = new Vertex();

        $graph->vertexStore->addVertex($v1);
        $graph->vertexStore->addVertex($v2);
        $graph->vertexStore->addVertex($v3);

        $stopVisitor = new VertexTraversalStopAtCurrentVisitor();
        $continueVisitor = new VertexTraversalContinueVisitorB();

        $traversal = new VertexTraversal([$stopVisitor, $continueVisitor]);
        $result = $traversal->traverse($graph);

        $this->assertSame([$v1->id->toString()], $stopVisitor->visited);
        $this->assertSame([$v1->id->toString()], $continueVisitor->visited);
        $this->assertTrue($result->has($v1->id, VertexTraversalStopAtCurrentVisitor::class));
        $this->assertTrue($result->has($v1->id, VertexTraversalContinueVisitorB::class));
        $this->assertFalse($result->has($v2->id, VertexTraversalStopAtCurrentVisitor::class));
    }

    #[Test]
    public function traverseStopsImmediatelyWithoutCallingRemainingVisitors(): void
    {
        $graph = new Graph();
        $v1 = new Vertex();
        $v2 = new Vertex();

        $graph->vertexStore->addVertex($v1);
        $graph->vertexStore->addVertex($v2);

        $stopVisitor = new VertexTraversalStopImmediatelyVisitor();
        $continueVisitor = new VertexTraversalContinueVisitorA();

        $traversal = new VertexTraversal([$stopVisitor, $continueVisitor]);
        $result = $traversal->traverse($graph);

        $this->assertSame([$v1->id->toString()], $stopVisitor->visited);
        $this->assertSame([], $continueVisitor->visited);
        $this->assertTrue($result->has($v1->id, VertexTraversalStopImmediatelyVisitor::class));
        $this->assertFalse($result->has($v1->id, VertexTraversalContinueVisitorA::class));
        $this->assertCount(1, $result->getAll());
    }
}
