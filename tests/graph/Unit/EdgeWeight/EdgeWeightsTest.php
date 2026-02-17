<?php

declare(strict_types=1);

namespace Tests\PhpArchitecture\Graph\Unit\EdgeWeight;

use PhpArchitecture\Graph\Edge\Identity\EdgeId;
use PhpArchitecture\Graph\EdgeWeight\EdgeWeights;
use PhpArchitecture\Graph\EdgeWeight\Exception\WeightNotFoundException;
use PhpArchitecture\Graph\EdgeWeight\Weight;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class EdgeWeightsTest extends TestCase
{
    #[Test]
    public function getAndValueReturnStoredWeight(): void
    {
        $edgeId = EdgeId::new();
        $weights = new EdgeWeights($edgeId, ['cost' => new Weight('cost', 5.5)]);

        $this->assertTrue($weights->has('cost'));
        $this->assertSame(5.5, $weights->value('cost'));
        $this->assertSame('cost', $weights->get('cost')->key);
    }

    #[Test]
    public function getThrowsForMissingWeight(): void
    {
        $weights = new EdgeWeights(EdgeId::new());

        $this->expectException(WeightNotFoundException::class);

        $weights->get('missing');
    }

    #[Test]
    public function replaceThrowsForMissingWeight(): void
    {
        $weights = new EdgeWeights(EdgeId::new());

        $this->expectException(WeightNotFoundException::class);

        $weights->replace(new Weight('cost', 1.0));
    }

    #[Test]
    public function fillWithKeepsExistingWhenOnlyMissingIsTrue(): void
    {
        $weights = new EdgeWeights(
            EdgeId::new(),
            ['cost' => new Weight('cost', 10.0)],
        );

        $weights->fillWith([
            new Weight('cost', 99.0),
            new Weight('time', 7.0),
        ]);

        $this->assertSame(10.0, $weights->value('cost'));
        $this->assertSame(7.0, $weights->value('time'));
    }

    #[Test]
    public function fillWithCanOverrideWhenOnlyMissingIsFalse(): void
    {
        $weights = new EdgeWeights(
            EdgeId::new(),
            ['cost' => new Weight('cost', 10.0)],
        );

        $weights->fillWith([
            new Weight('cost', 20.0),
        ], false);

        $this->assertSame(20.0, $weights->value('cost'));
    }

    #[Test]
    public function removeAndClearMutateCollection(): void
    {
        $weights = new EdgeWeights(
            EdgeId::new(),
            [
                'cost' => new Weight('cost', 1.0),
                'time' => new Weight('time', 2.0),
            ],
        );

        $weights->remove('time');
        $this->assertFalse($weights->has('time'));

        $weights->clear();
        $this->assertSame([], $weights->all());
    }

    #[Test]
    public function withEdgeIdReturnsNewInstanceWithSameWeights(): void
    {
        $weights = new EdgeWeights(
            EdgeId::new(),
            ['cost' => new Weight('cost', 2.0)],
        );
        $newEdgeId = EdgeId::new();

        $copy = $weights->withEdgeId($newEdgeId);

        $this->assertNotSame($weights, $copy);
        $this->assertSame($newEdgeId, $copy->edgeId);
        $this->assertSame(2.0, $copy->value('cost'));
    }
}
