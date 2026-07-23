<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Comment;

use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\Placement;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\GroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\OptionalRawAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawRegionAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Structure\StructureAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeOrigin;
use PhpArchitecture\Parser\Foundation\Parsing\Model\SequenceNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\EmptyLineNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\InlineWsNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\LeadingWsNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\TrailingWsNode;

class CommentEndLineNode extends SequenceNode
{
    /** @var GroupAttribute<LeadingWsNode|EmptyLineNode|TrailingWsNode|InlineWsNode> */
    public GroupAttribute $trivia { get => $this->attributes[0]; }
    public StructureAttribute $asterisk { get => $this->attributes[1]; }
    public OptionalRawAttribute $leadingWs { get => $this->attributes[2]; }
    public OptionalRawAttribute $trailingWs { get => $this->attributes[3]; }
    public StructureAttribute $closingAsterisk { get => $this->attributes[4]; }
    public StructureAttribute $blockCommentEnd { get => $this->attributes[5]; }

    public static function create(?string $leadingWs, ?string $trailingWs = null): self
    {
        return new self(
            name: 'commentEndLine',
            origin: NodeOrigin::Sequence,
            attributes: [
                new GroupAttribute('trivia', []),
                new StructureAttribute(true, 'asterisk', '*'),
                new OptionalRawAttribute(
                    $leadingWs !== null
                        ? new RawRegionAttribute(opener: null, content: $leadingWs, closer: null, name: 'leadingWs', anchorName: 'leadingWs')
                        : null,
                    name: 'leadingWs',
                    anchorName: 'leadingWs',
                ),
                new OptionalRawAttribute(
                    $trailingWs !== null
                        ? new RawRegionAttribute(opener: null, content: $trailingWs, closer: null, name: 'trailingWs', anchorName: 'trailingWs')
                        : null,
                    name: 'trailingWs',
                    anchorName: 'trailingWs',
                ),
                new StructureAttribute(true, 'closingAsterisk', '*'),
                new StructureAttribute(true, 'blockCommentEnd', '*/'),
            ],
            parent: null,
        );
    }

    public function addNodeToTrivia(LeadingWsNode|EmptyLineNode|TrailingWsNode|InlineWsNode $node, Placement $placement = Placement::After, int $offset = -1): self
    {
        $this->trivia->addNode($node->setParent($this), $placement, $offset);
        return $this;
    }

    /** @return array<LeadingWsNode|EmptyLineNode|TrailingWsNode|InlineWsNode> */
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

    public function getRawLeadingWs(): ?string
    {
        return $this->leadingWs->raw?->content;
    }

    public function setRawLeadingWs(?string $value): self
    {
        if ($value === null) {
            $this->leadingWs->raw = null;
        } elseif ($this->leadingWs->raw instanceof RawRegionAttribute) {
            $this->leadingWs->raw->content = $value;
        } else {
            $this->leadingWs->raw = new RawRegionAttribute(opener: null, content: $value, closer: null, name: 'leadingWs', anchorName: 'leadingWs');
        }
        return $this;
    }

    public function getRawTrailingWs(): ?string
    {
        return $this->trailingWs->raw?->content;
    }

    public function setRawTrailingWs(?string $value): self
    {
        if ($value === null) {
            $this->trailingWs->raw = null;
        } elseif ($this->trailingWs->raw instanceof RawRegionAttribute) {
            $this->trailingWs->raw->content = $value;
        } else {
            $this->trailingWs->raw = new RawRegionAttribute(opener: null, content: $value, closer: null, name: 'trailingWs', anchorName: 'trailingWs');
        }
        return $this;
    }
}
