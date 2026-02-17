<?php

declare(strict_types=1);

namespace Tests\PhpArchitecture\Graph\Unit;

use PhpArchitecture\Graph\EdgeWeight\Config\WeightConfig;
use PhpArchitecture\Graph\GraphConfig;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class GraphConfigTest extends TestCase
{
    #[Test]
    public function usesEdgeWeightsReturnsFalseWhenWeightConfigIsMissing(): void
    {
        $config = new GraphConfig();

        $this->assertFalse($config->usesEdgeWeights());
    }

    #[Test]
    public function usesEdgeWeightsReturnsTrueWhenWeightConfigIsProvided(): void
    {
        $config = new GraphConfig(weightConfig: new WeightConfig());

        $this->assertTrue($config->usesEdgeWeights());
    }
}
