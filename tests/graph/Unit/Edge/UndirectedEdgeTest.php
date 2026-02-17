<?php

declare(strict_types=1);

namespace Tests\PhpArchitecture\Graph\Unit\Edge;

use PhpArchitecture\Graph\Edge\Identity\EdgeId;
use PhpArchitecture\Graph\Edge\UndirectedEdge;
use PhpArchitecture\Graph\Vertex\Vertex;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class UndirectedEdgeTest extends TestCase
{
    #[Test]
    public function constructorStoresVerticesAndMetadata(): void
    {
        $u = new Vertex();
        $v = new Vertex();
        $edge = new UndirectedEdge($u, $v, metadata: ['kind' => 'friend']);

        $this->assertSame($u->id, $edge->u());
        $this->assertSame($v->id, $edge->v());
        $this->assertSame(['kind' => 'friend'], $edge->metadata);
        $this->assertInstanceOf(EdgeId::class, $edge->id);
    }
}
