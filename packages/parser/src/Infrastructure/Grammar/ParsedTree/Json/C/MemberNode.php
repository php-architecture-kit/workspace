<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\C;

use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\GroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\NodeAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawRegionAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Structure\StructureAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeOrigin;
use PhpArchitecture\Parser\Foundation\Parsing\Model\SequenceNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\EmptyLineNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\InlineWsNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\LeadingWsNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\TrailingWsNode;

class MemberNode extends SequenceNode
{
    public RawRegionAttribute $identifier { get => $this->attributes[0]; }

    /** @var GroupAttribute<InlineWsNode|BlockCommentNode|EmptyLineNode|TrailingWsNode|LeadingWsNode> */
    public GroupAttribute $trivia0 { get => $this->attributes[1]; }

    public StructureAttribute $colon { get => $this->attributes[2]; }

    /** @var GroupAttribute<TrailingWsNode|InlineWsNode|BlockCommentNode|LeadingWsNode|EmptyLineNode> */
    public GroupAttribute $trivia1 { get => $this->attributes[3]; }

    /** @var NodeAttribute<PrimitiveNode|ArrayNode|ObjectNode> */
    public NodeAttribute $value { get => $this->attributes[4]; }

    public static function create(string $identifier, PrimitiveNode|ArrayNode|ObjectNode $value): self
    {
        $node = new self(
            name: 'member',
            origin: NodeOrigin::Sequence,
            attributes: [
                new RawRegionAttribute(
                    opener: '"',
                    closer: '"',
                    content: $identifier,
                    name: 'doubleQuotedString',
                    anchorName: 'identifier',
                ),
                new GroupAttribute('trivia0', []),
                new StructureAttribute(true, 'colon', ':'),
                new GroupAttribute('trivia1', []),
                NodeAttribute::fromNode($value),
            ],
            parent: null,
        );
        $value->setParent($node);

        return $node;
    }

    public function getRawIdentifier(): string
    {
        return $this->identifier->content;
    }

    public function setRawIdentifier(string $identifier): self
    {
        $this->identifier->content = $identifier;
        return $this;
    }

    public function getNodeValue(): PrimitiveNode|ArrayNode|ObjectNode
    {
        /** @var PrimitiveNode|ArrayNode|ObjectNode $node */
        $node = $this->value->node;
        return $node;
    }

    public function setNodeValue(PrimitiveNode|ArrayNode|ObjectNode $value): self
    {
        $this->attributes[4] = NodeAttribute::fromNode($value->setParent($this));
        return $this;
    }
}
