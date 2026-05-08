<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\AST\Factory;

use PhpArchitecture\Parser\Foundation\AST\AstGraph;
use PhpArchitecture\Parser\Foundation\AST\Definition\NodeDefinition;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Node;

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
