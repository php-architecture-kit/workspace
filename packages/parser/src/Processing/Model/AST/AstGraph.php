<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Processing\Model\AST;

use PhpArchitecture\Graph\Graph;
use PhpArchitecture\Graph\GraphConfig;

class AstGraph extends Graph
{
    public function __construct()
    {
        parent::__construct(
            new GraphConfig(
                allowSelfLoop: false,
                allowMultiEdge: true,
                allowCyclicEdge: false,
                weightConfig: null
            )
        );
    }
}
