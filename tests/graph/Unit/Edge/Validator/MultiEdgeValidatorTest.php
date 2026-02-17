<?php

declare(strict_types=1);

namespace Tests\PhpArchitecture\Graph\Unit\Edge\Validator;

use PhpArchitecture\Graph\Edge\DirectedEdge;
use PhpArchitecture\Graph\Edge\Exception\MultiEdgeException;
use PhpArchitecture\Graph\Edge\Validator\MultiEdgeValidator;
use PhpArchitecture\Graph\Graph;
use PhpArchitecture\Graph\Vertex\Vertex;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MultiEdgeValidatorTest extends TestCase
{
    #[Test]
    public function validateThrowsExceptionForParallelEdgeInAnyDirection(): void
    {
        $graph = new Graph();
        $u = new Vertex();
        $v = new Vertex();
        $graph->vertexStore->addVertex($u);
        $graph->vertexStore->addVertex($v);
        $graph->edgeStore->addEdge(new DirectedEdge($u, $v));

        $validator = new MultiEdgeValidator();

        $this->expectException(MultiEdgeException::class);

        $validator->validate(new DirectedEdge($v, $u), $graph);
    }
}
