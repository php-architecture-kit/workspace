<?php

declare(strict_types=1);

namespace Tests\PhpArchitecture\Graph\Unit\Navigation\Traversal;

use PhpArchitecture\Graph\Navigation\Traversal\VertexTraversalResult;
use PhpArchitecture\Graph\Navigation\Traversal\VertexVisitorInterface;
use PhpArchitecture\Graph\Navigation\Traversal\VisitAction;
use PhpArchitecture\Graph\Navigation\Traversal\VisitResult;
use PhpArchitecture\Graph\Vertex\Vertex;
use PhpArchitecture\Graph\Vertex\VertexInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class VertexTraversalResultVisitorA implements VertexVisitorInterface
{
    public function visit(VertexInterface $vertex): VisitResult
    {
        return new VisitResult(VisitAction::Continue);
    }
}

class VertexTraversalResultVisitorB implements VertexVisitorInterface
{
    public function visit(VertexInterface $vertex): VisitResult
    {
        return new VisitResult(VisitAction::Continue);
    }
}

class VertexTraversalResultTest extends TestCase
{
    #[Test]
    public function addGetAndHasWorkForStoredResult(): void
    {
        $vertex = new Vertex();
        $stored = new VisitResult(VisitAction::Continue);

        $result = new VertexTraversalResult();
        $result->add($vertex->id, VertexTraversalResultVisitorA::class, $stored);

        $this->assertTrue($result->has($vertex->id, VertexTraversalResultVisitorA::class));
        $this->assertSame($stored, $result->get($vertex->id, VertexTraversalResultVisitorA::class));
        $this->assertFalse($result->has($vertex->id, VertexTraversalResultVisitorB::class));
        $this->assertNull($result->get($vertex->id, VertexTraversalResultVisitorB::class));
    }

    #[Test]
    public function getByVertexGetByVisitorAndGetAllReturnExpectedSlices(): void
    {
        $v1 = new Vertex();
        $v2 = new Vertex();

        $v1A = new VisitResult(VisitAction::Continue);
        $v1B = new VisitResult(VisitAction::StopAtCurrentEntity);
        $v2A = new VisitResult(VisitAction::StopImmediately);

        $result = new VertexTraversalResult();
        $result->add($v1->id, VertexTraversalResultVisitorA::class, $v1A);
        $result->add($v1->id, VertexTraversalResultVisitorB::class, $v1B);
        $result->add($v2->id, VertexTraversalResultVisitorA::class, $v2A);

        $this->assertSame(
            [
                VertexTraversalResultVisitorA::class => $v1A,
                VertexTraversalResultVisitorB::class => $v1B,
            ],
            $result->getByVertex($v1->id),
        );
        $this->assertSame(
            [
                $v1->id->toString() => $v1A,
                $v2->id->toString() => $v2A,
            ],
            $result->getByVisitor(VertexTraversalResultVisitorA::class),
        );
        $this->assertSame(
            [
                $v1->id->toString() => [
                    VertexTraversalResultVisitorA::class => $v1A,
                    VertexTraversalResultVisitorB::class => $v1B,
                ],
                $v2->id->toString() => [
                    VertexTraversalResultVisitorA::class => $v2A,
                ],
            ],
            $result->getAll(),
        );
    }
}
