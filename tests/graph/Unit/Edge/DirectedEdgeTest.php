<?php

declare(strict_types=1);

namespace Tests\PhpArchitecture\Graph\Unit\Edge;

use PhpArchitecture\Graph\Edge\DirectedEdge;
use PhpArchitecture\Graph\Edge\Identity\EdgeId;
use PhpArchitecture\Graph\Vertex\Vertex;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DirectedEdgeTest extends TestCase
{
    #[Test]
    public function constructorStoresTailHeadAndMetadata(): void
    {
        $tail = new Vertex();
        $head = new Vertex();
        $edge = new DirectedEdge($tail, $head, metadata: ['label' => 'depends_on']);

        $this->assertSame($tail->id, $edge->u());
        $this->assertSame($head->id, $edge->v());
        $this->assertSame(['label' => 'depends_on'], $edge->metadata);
        $this->assertInstanceOf(EdgeId::class, $edge->id);
    }
}
