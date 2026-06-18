<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Parsing\Model;

/**
 * @deprecated Transitional shim. The parsed tree now materializes shape-specific
 * subclasses ({@see LeafNode}, {@see GroupNode}, {@see SequenceNode}) via
 * {@see \PhpArchitecture\Parser\Foundation\Parsing\Factory\NodeFactory}. This
 * concrete class is kept only so legacy/generated facades and the out-of-scope AST
 * layer keep compiling during the refactor; it will be removed once every in-scope
 * consumer is re-parented onto a shape subclass.
 */
class Node extends AbstractNode
{
}
