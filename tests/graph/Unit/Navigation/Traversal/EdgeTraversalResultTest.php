<?php

declare(strict_types=1);

namespace Tests\PhpArchitecture\Graph\Unit\Navigation\Traversal;

use PhpArchitecture\Graph\Edge\DirectedEdge;
use PhpArchitecture\Graph\Edge\DirectedEdgeInterface;
use PhpArchitecture\Graph\Navigation\Traversal\EdgeTraversalResult;
use PhpArchitecture\Graph\Navigation\Traversal\EdgeVisitorInterface;
use PhpArchitecture\Graph\Edge\UndirectedEdgeInterface;
use PhpArchitecture\Graph\Navigation\Traversal\VisitAction;
use PhpArchitecture\Graph\Navigation\Traversal\VisitResult;
use PhpArchitecture\Graph\Vertex\Vertex;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class EdgeTraversalResultVisitorA implements EdgeVisitorInterface
{
    public function visit(DirectedEdgeInterface|UndirectedEdgeInterface $edge): VisitResult
    {
        return new VisitResult(VisitAction::Continue);
    }
}

class EdgeTraversalResultVisitorB implements EdgeVisitorInterface
{
    public function visit(DirectedEdgeInterface|UndirectedEdgeInterface $edge): VisitResult
    {
        return new VisitResult(VisitAction::Continue);
    }
}

class EdgeTraversalResultTest extends TestCase
{
    #[Test]
    public function addGetAndHasWorkForStoredResult(): void
    {
        $edge = new DirectedEdge(new Vertex(), new Vertex());
        $stored = new VisitResult(VisitAction::Continue);

        $result = new EdgeTraversalResult();
        $result->add($edge->id, EdgeTraversalResultVisitorA::class, $stored);

        $this->assertTrue($result->has($edge->id, EdgeTraversalResultVisitorA::class));
        $this->assertSame($stored, $result->get($edge->id, EdgeTraversalResultVisitorA::class));
        $this->assertFalse($result->has($edge->id, EdgeTraversalResultVisitorB::class));
        $this->assertNull($result->get($edge->id, EdgeTraversalResultVisitorB::class));
    }

    #[Test]
    public function getByEdgeGetByVisitorAndGetAllReturnExpectedSlices(): void
    {
        $e1 = new DirectedEdge(new Vertex(), new Vertex());
        $e2 = new DirectedEdge(new Vertex(), new Vertex());

        $e1A = new VisitResult(VisitAction::Continue);
        $e1B = new VisitResult(VisitAction::StopAtCurrentEntity);
        $e2A = new VisitResult(VisitAction::StopImmediately);

        $result = new EdgeTraversalResult();
        $result->add($e1->id, EdgeTraversalResultVisitorA::class, $e1A);
        $result->add($e1->id, EdgeTraversalResultVisitorB::class, $e1B);
        $result->add($e2->id, EdgeTraversalResultVisitorA::class, $e2A);

        $this->assertSame(
            [
                EdgeTraversalResultVisitorA::class => $e1A,
                EdgeTraversalResultVisitorB::class => $e1B,
            ],
            $result->getByEdge($e1->id),
        );
        $this->assertSame(
            [
                $e1->id->toString() => $e1A,
                $e2->id->toString() => $e2A,
            ],
            $result->getByVisitor(EdgeTraversalResultVisitorA::class),
        );
        $this->assertSame(
            [
                $e1->id->toString() => [
                    EdgeTraversalResultVisitorA::class => $e1A,
                    EdgeTraversalResultVisitorB::class => $e1B,
                ],
                $e2->id->toString() => [
                    EdgeTraversalResultVisitorA::class => $e2A,
                ],
            ],
            $result->getAll(),
        );
    }
}
