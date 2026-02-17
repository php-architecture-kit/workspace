<?php

declare(strict_types=1);

namespace Tests\PhpArchitecture\Graph\Unit\Edge\Validator;

use PhpArchitecture\Graph\Edge\DirectedEdge;
use PhpArchitecture\Graph\Edge\Exception\CyclicEdgeException;
use PhpArchitecture\Graph\Edge\Validator\CyclicEdgeValidator;
use PhpArchitecture\Graph\Graph;
use PhpArchitecture\Graph\Vertex\Vertex;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CyclicEdgeValidatorTest extends TestCase
{
    #[Test]
    public function validateThrowsExceptionWhenEdgeWouldCloseCycle(): void
    {
        $graph = new Graph();
        $v1 = new Vertex();
        $v2 = new Vertex();
        $v3 = new Vertex();

        $graph->vertexStore->addVertex($v1);
        $graph->vertexStore->addVertex($v2);
        $graph->vertexStore->addVertex($v3);
        $graph->edgeStore->addEdge(new DirectedEdge($v1, $v2));
        $graph->edgeStore->addEdge(new DirectedEdge($v2, $v3));

        $validator = new CyclicEdgeValidator();

        $this->expectException(CyclicEdgeException::class);

        $validator->validate(new DirectedEdge($v3, $v1), $graph);
    }
}
