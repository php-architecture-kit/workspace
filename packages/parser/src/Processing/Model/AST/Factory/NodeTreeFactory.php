<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Processing\Model\Ast\Factory;

use PhpArchitecture\Parser\Parsing\Model\Node;
use PhpArchitecture\Parser\Processing\Model\AST\AstGraph;
use PhpArchitecture\Parser\Processing\Model\Ast\Definition\NodeDefinition;

class NodeTreeFactory
{
    public function __construct(
        private readonly NodeDefinition $rootDefinition,
    ) {}

    public function createNodeTree(AstGraph $graph): Node 
    {

    }

    // public function createNode()
}
