<?php

declare(strict_types=1);

namespace Tests\PhpArchitecture\Graph\Unit\EdgeWeight\Config;

use PhpArchitecture\Graph\Edge\DirectedEdge;
use PhpArchitecture\Graph\Edge\Identity\EdgeId;
use PhpArchitecture\Graph\EdgeWeight\Config\WeightConfig;
use PhpArchitecture\Graph\Vertex\Vertex;
use PhpArchitecture\Graph\Vertex\Identity\VertexId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ChildDirectedEdge extends DirectedEdge
{
}

class WeightConfigTest extends TestCase
{
    #[Test]
    public function defineThrowsForNonEdgeClass(): void
    {
        $config = new WeightConfig();

        $this->expectException(\InvalidArgumentException::class);

        $config->define(\stdClass::class, ['cost' => 1.0]);
    }

    #[Test]
    public function defaultThrowsForNonEdgeClass(): void
    {
        $config = new WeightConfig();

        $this->expectException(\InvalidArgumentException::class);

        $config->default(\stdClass::class);
    }

    #[Test]
    public function defineThrowsForInvalidKeyOrValue(): void
    {
        $config = new WeightConfig();

        $this->expectException(\InvalidArgumentException::class);
        $config->define(DirectedEdge::class, ['' => 1.0]);
    }

    #[Test]
    public function defineThrowsForNonFiniteValue(): void
    {
        $config = new WeightConfig();

        $this->expectException(\InvalidArgumentException::class);
        $config->define(DirectedEdge::class, ['cost' => INF]);
    }

    #[Test]
    public function defaultResolvesInheritanceAndOverrideOrder(): void
    {
        $config = new WeightConfig([
            DirectedEdge::class => ['base' => 1.0, 'directed' => 2.0, 'override' => 2.0],
            ChildDirectedEdge::class => ['child' => 3.0, 'override' => 3.0],
        ]);

        $resolved = $config->default(ChildDirectedEdge::class);

        $this->assertSame(1.0, $resolved->value('base'));
        $this->assertSame(2.0, $resolved->value('directed'));
        $this->assertSame(3.0, $resolved->value('child'));
        $this->assertSame(3.0, $resolved->value('override'));
        $this->assertTrue($resolved->edgeId->equals(EdgeId::nil()));
    }

    #[Test]
    public function defineInvalidatesCachedDefaults(): void
    {
        $config = new WeightConfig([
            DirectedEdge::class => ['cost' => 1.0],
        ]);

        $this->assertSame(1.0, $config->default(DirectedEdge::class)->value('cost'));

        $config->define(DirectedEdge::class, ['cost' => 9.0]);

        $this->assertSame(9.0, $config->default(DirectedEdge::class)->value('cost'));
    }
}
