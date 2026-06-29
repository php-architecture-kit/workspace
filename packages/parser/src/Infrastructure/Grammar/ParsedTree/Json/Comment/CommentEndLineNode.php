<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Comment;

use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\Placement;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\GroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\OptionalRawAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Structure\StructureAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeOrigin;
use PhpArchitecture\Parser\Foundation\Parsing\Model\SequenceNode;

class CommentEndLineNode extends SequenceNode
{
    public GroupAttribute $trivia { get => $this->attributes[0]; }

    public StructureAttribute $asterisk { get => $this->attributes[1]; }

    public OptionalRawAttribute $leadingWs { get => $this->attributes[2]; }

    public OptionalRawAttribute $trailingWs { get => $this->attributes[3]; }

    public StructureAttribute $closingAsterisk { get => $this->attributes[4]; }

    public StructureAttribute $blockCommentEnd { get => $this->attributes[5]; }

    public static function create(): self
    {
        return new self(
            name: 'commentEndLine',
            origin: NodeOrigin::Sequence,
            attributes: [
                new GroupAttribute('trivia', []),
                new StructureAttribute(true, 'asterisk', '*'),
                new StructureAttribute(true, 'closingAsterisk', '*'),
                new StructureAttribute(true, 'blockCommentEnd', '*/'),
            ],
            parent: null,
        );
    }

    public function addNodeToTrivia( $node, Placement $placement = Placement::After, int $offset = -1): self
    {
        $this->trivia->addNode($node->setParent($this), $placement, $offset);
        return $this;
    }

    /** @return array<> */
    public function getNodesFromTrivia(?callable $filter = null): array
    {
        return $this->trivia->getNodes($filter);
    }

    public function removeNodeFromTriviaByOffset(int $offset): self
    {
        $this->trivia->removeNodeByOffset($offset);
        return $this;
    }

    /** @param callable(NodeInterface):bool $filter true - stay, false - remove */
    public function removeNodeFromTriviaByFilter(callable $filter): self
    {
        $this->trivia->removeNodeByFilter($filter);
        return $this;
    }
}
