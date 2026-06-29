<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Env\Environment;

use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\Placement;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\GroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\OptionalRawAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawContentAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Structure\StructureAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeOrigin;
use PhpArchitecture\Parser\Foundation\Parsing\Model\SequenceNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\EmptyLineNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\InlineWsNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\LeadingWsNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\TrailingWsNode;

class LineCommentNode extends SequenceNode
{
    public StructureAttribute $hash { get => $this->attributes[0]; }

    public OptionalRawAttribute $leadingWs { get => $this->attributes[1]; }

    public RawContentAttribute $content { get => $this->attributes[2]; }

    /** @var GroupAttribute<TrailingWsNode|EmptyLineNode|LeadingWsNode|InlineWsNode> */
    public GroupAttribute $trivia { get => $this->attributes[3]; }

    public static function create(string $content): self
    {
        return new self(
            name: 'lineComment',
            origin: NodeOrigin::Sequence,
            attributes: [
                new StructureAttribute(true, 'hash', '#'),
                new RawContentAttribute($content),
                new GroupAttribute('trivia', []),
            ],
            parent: null,
        );
    }

    public function getRawContent(): string
    {
        return $this->content->content;
    }

    public function setRawContent(string $value): self
    {
        $this->content->content = $value;
        return $this;
    }

    public function addNodeToTrivia(TrailingWsNode|EmptyLineNode|LeadingWsNode|InlineWsNode $node, Placement $placement = Placement::After, int $offset = -1): self
    {
        $this->trivia->addNode($node->setParent($this), $placement, $offset);
        return $this;
    }

    /** @return array<TrailingWsNode|EmptyLineNode|LeadingWsNode|InlineWsNode> */
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
