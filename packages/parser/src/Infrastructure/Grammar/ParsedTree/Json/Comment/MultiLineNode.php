<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Comment;

use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\Placement;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\GroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\NodeAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeOrigin;
use PhpArchitecture\Parser\Foundation\Parsing\Model\SequenceNode;

class MultiLineNode extends SequenceNode
{
    /** @var NodeAttribute<CommentStartLineNode> */
    public NodeAttribute $commentStartLine { get => $this->attributes[0]; }

    /** @var GroupAttribute<CommentMidLineNode|CommentEmptyLineNode> */
    public GroupAttribute $body { get => $this->attributes[1]; }

    /** @var NodeAttribute<CommentEndLineNode> */
    public NodeAttribute $commentEndLine { get => $this->attributes[2]; }

    public static function create(CommentStartLineNode $commentStartLine, CommentEndLineNode $commentEndLine): self
    {
        $node = new self(
            name: 'multiLine',
            origin: NodeOrigin::Sequence,
            attributes: [
                NodeAttribute::fromNode($commentStartLine),
                new GroupAttribute('body', []),
                NodeAttribute::fromNode($commentEndLine),
            ],
            parent: null,
        );
        $commentStartLine->setParent($node);
        $commentEndLine->setParent($node);

        return $node;
    }

    public function getNodeCommentStartLine(): CommentStartLineNode
    {
        /** @var CommentStartLineNode $node */
        $node = $this->commentStartLine->node;
        return $node;
    }

    public function setNodeCommentStartLine(CommentStartLineNode $value): self
    {
        $this->attributes[0] = NodeAttribute::fromNode($value->setParent($this));
        return $this;
    }

    public function addNodeToBody(CommentMidLineNode|CommentEmptyLineNode $node, Placement $placement = Placement::After, int $offset = -1): self
    {
        $this->body->addNode($node->setParent($this), $placement, $offset);
        return $this;
    }

    /** @return array<CommentMidLineNode|CommentEmptyLineNode> */
    public function getNodesFromBody(?callable $filter = null): array
    {
        return $this->body->getNodes($filter);
    }

    public function removeNodeFromBodyByOffset(int $offset): self
    {
        $this->body->removeNodeByOffset($offset);
        return $this;
    }

    /** @param callable(NodeInterface):bool $filter true - stay, false - remove */
    public function removeNodeFromBodyByFilter(callable $filter): self
    {
        $this->body->removeNodeByFilter($filter);
        return $this;
    }

    public function getNodeCommentEndLine(): CommentEndLineNode
    {
        /** @var CommentEndLineNode $node */
        $node = $this->commentEndLine->node;
        return $node;
    }

    public function setNodeCommentEndLine(CommentEndLineNode $value): self
    {
        $this->attributes[2] = NodeAttribute::fromNode($value->setParent($this));
        return $this;
    }
}
