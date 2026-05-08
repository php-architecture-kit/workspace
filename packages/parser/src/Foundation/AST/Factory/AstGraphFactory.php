<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\AST\Factory;

use PhpArchitecture\Parser\Foundation\AST\AstGraph;
use PhpArchitecture\Parser\Foundation\AST\Definition\NodeDefinition;
use PhpArchitecture\Parser\Foundation\AST\Model\AstNode;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Node;

class AstGraphFactory
{
    public function __construct(
        private readonly NodeDefinition $rootDefinition,
    ) {}

    public function createAstGraph(Node $treeRoot): AstGraph 
    {

    }

    public function createNodeRecursive(
        NodeDefinition $definition,
        Node $node,
        AstGraph $graph,
    ): AstNode {
        
    }
}
