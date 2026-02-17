<?php

declare(strict_types=1);

namespace Tests\PhpArchitecture\Graph\Unit\Vertex;

use PhpArchitecture\Graph\Edge\DirectedEdge;
use PhpArchitecture\Graph\Edge\DirectedEdgeInterface;
use PhpArchitecture\Graph\Edge\EdgeContext;
use PhpArchitecture\Graph\Edge\UndirectedEdgeInterface;
use PhpArchitecture\Graph\Graph;
use PhpArchitecture\Graph\GraphNavigator;
use PhpArchitecture\Graph\Vertex\Vertex;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class VertexContextTest extends TestCase
{
    #[Test]
    public function shortestPathToReturnsEmptyWhenSourceAndTargetAreTheSame(): void
    {
        $graph = new Graph();
        $vertex = new Vertex();
        $graph->vertexStore->addVertex($vertex);

        $path = (new GraphNavigator($graph))
            ->shortestPathTo($vertex->id(), $vertex->id());

        $this->assertSame([], $path);
    }

    #[Test]
    public function shortestPathToReturnsShortestPathAsEdgeContexts(): void
    {
        $scenario = $this->createGraphWithAlternativePaths();

        $path = (new GraphNavigator($scenario['graph']))
            ->shortestPathTo($scenario['a']->id(), $scenario['d']->id());

        $this->assertSame(
            [
                $scenario['ae']->id()->toString(),
                $scenario['ed']->id()->toString(),
            ],
            array_map(
                static fn(EdgeContext $context): string => $context->edge->id()->toString(),
                $path,
            ),
        );
    }

    #[Test]
    public function shortestPathToRespectsEdgeFilter(): void
    {
        $scenario = $this->createGraphWithAlternativePaths();

        $path = (new GraphNavigator($scenario['graph']))
            ->shortestPathTo(
                $scenario['a']->id(),
                $scenario['d']->id(),
                static fn(DirectedEdgeInterface|UndirectedEdgeInterface $edge): bool => !$edge->id()->equals($scenario['ae']->id()),
            );

        $this->assertSame(
            [
                $scenario['ab']->id()->toString(),
                $scenario['bc']->id()->toString(),
                $scenario['cd']->id()->toString(),
            ],
            array_map(
                static fn(EdgeContext $context): string => $context->edge->id()->toString(),
                $path,
            ),
        );
    }

    #[Test]
    public function shortestPathToReturnsEmptyWhenNoPathMatchesFilter(): void
    {
        $scenario = $this->createGraphWithAlternativePaths();

        $path = (new GraphNavigator($scenario['graph']))
            ->shortestPathTo(
                $scenario['a']->id(),
                $scenario['d']->id(),
                static fn(DirectedEdgeInterface|UndirectedEdgeInterface $edge): bool =>
                    !$edge->id()->equals($scenario['ed']->id())
                    && !$edge->id()->equals($scenario['cd']->id()),
            );

        $this->assertSame([], $path);
    }

    /**
     * @return array{
     *   graph: Graph,
     *   a: Vertex,
     *   b: Vertex,
     *   c: Vertex,
     *   d: Vertex,
     *   e: Vertex,
     *   ab: DirectedEdge,
     *   bc: DirectedEdge,
     *   cd: DirectedEdge,
     *   ae: DirectedEdge,
     *   ed: DirectedEdge
     * }
     */
    private function createGraphWithAlternativePaths(): array
    {
        $graph = new Graph();

        $a = new Vertex();
        $b = new Vertex();
        $c = new Vertex();
        $d = new Vertex();
        $e = new Vertex();

        $graph->vertexStore->addVertex($a);
        $graph->vertexStore->addVertex($b);
        $graph->vertexStore->addVertex($c);
        $graph->vertexStore->addVertex($d);
        $graph->vertexStore->addVertex($e);

        $ab = new DirectedEdge($a, $b);
        $bc = new DirectedEdge($b, $c);
        $cd = new DirectedEdge($c, $d);
        $ae = new DirectedEdge($a, $e);
        $ed = new DirectedEdge($e, $d);

        $graph->edgeStore->addEdge($ab);
        $graph->edgeStore->addEdge($bc);
        $graph->edgeStore->addEdge($cd);
        $graph->edgeStore->addEdge($ae);
        $graph->edgeStore->addEdge($ed);

        return [
            'graph' => $graph,
            'a' => $a,
            'b' => $b,
            'c' => $c,
            'd' => $d,
            'e' => $e,
            'ab' => $ab,
            'bc' => $bc,
            'cd' => $cd,
            'ae' => $ae,
            'ed' => $ed,
        ];
    }
}
