<?php

declare(strict_types=1);

namespace Tests\PhpArchitecture\Graph\Unit;

use PhpArchitecture\Graph\Exception\GraphGarbageCollectedException;
use PhpArchitecture\Graph\Exception\GraphNotSetException;
use PhpArchitecture\Graph\Graph;
use PhpArchitecture\Graph\GraphTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class GraphTraitTestObject
{
    use GraphTrait;

    public function graphPublic(): Graph
    {
        return $this->graph();
    }
}

class GraphTraitTest extends TestCase
{
    #[Test]
    public function graphThrowsExceptionWhenGraphIsNotSet(): void
    {
        $object = new GraphTraitTestObject();

        $this->expectException(GraphNotSetException::class);

        $object->graphPublic();
    }

    #[Test]
    public function graphReturnsGraphWhenItIsSet(): void
    {
        $object = new GraphTraitTestObject();
        $graph = new Graph();

        $object->setGraph($graph);

        $this->assertSame($graph, $object->graphPublic());
    }

    #[Test]
    public function graphThrowsExceptionWhenGraphWeakReferenceIsGarbageCollected(): void
    {
        $object = new GraphTraitTestObject();
        $graph = new Graph();
        $object->setGraph($graph);

        unset($graph);
        gc_collect_cycles();

        $this->expectException(GraphGarbageCollectedException::class);

        $object->graphPublic();
    }

    #[Test]
    public function unsetGraphRemovesGraphReference(): void
    {
        $object = new GraphTraitTestObject();
        $object->setGraph(new Graph());
        $object->unsetGraph();

        $this->expectException(GraphNotSetException::class);

        $object->graphPublic();
    }
}
