<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Processing\Model\Ast\Factory;

use PhpArchitecture\Parser\Parsing\Model\Node;
use PhpArchitecture\Parser\Processing\Model\AST\AstGraph;
use PhpArchitecture\Parser\Processing\Model\Ast\AstNode;
use PhpArchitecture\Parser\Processing\Model\Ast\Definition\ChildDefinition;
use PhpArchitecture\Parser\Processing\Model\Ast\Definition\NodeDefinition;

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
