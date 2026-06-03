<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\TreeSchema\Model\Json\C;

use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\ChoiceAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\GroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\NodeAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\RawRegionAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\StructureAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Node;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\EmptyLineNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\InlineWsNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\LeadingWsNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\TrailingWsNode;

class MemberNode extends Node
{
    public RawRegionAttribute $identifier { get => $this->attributes[0]; }

    /** @var GroupAttribute<InlineWsNode|EmptyLineNode|TrailingWsNode|LeadingWsNode> */
    public GroupAttribute $trivia0 { get => $this->attributes[1]; }
    public StructureAttribute $colon { get => $this->attributes[2]; }

    /** @var GroupAttribute<TrailingWsNode|InlineWsNode|BlockCommentNode|LeadingWsNode|EmptyLineNode> */
    public GroupAttribute $trivia1 { get => $this->attributes[3]; }

    /** @var ChoiceAttribute<PrimitiveNode|ArrayNode|ObjectNode> */
    public ChoiceAttribute $value { get => $this->attributes[4]; }

    public static function create(string $identifier): self
    {
        return new self(
            name: 'member',
            attributes: [
            new RawRegionAttribute(
                opener: new StructureAttribute(true, 'doubleQuote', '"'),
                closer: new StructureAttribute(true, 'doubleQuote', '"'),
                content: $identifier,
                name: 'string',
                anchorName: 'identifier',
            ),
            new GroupAttribute('trivia0', []),
            new StructureAttribute(true, 'colon', ':'),
            new GroupAttribute('trivia1', []),
            new ChoiceAttribute('value', ['array', 'object', 'primitive'], null),
            ],
            parent: null,
        );
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

    public function getNodeValue(): PrimitiveNode|ArrayNode|ObjectNode|null
    {
        /** @var ?NodeAttribute $attribute */
        $attribute = $this->value->selected;
        return $attribute?->node;
    }

    public function setNodeValue(PrimitiveNode|ArrayNode|ObjectNode $value): self
    {
        $this->value->setSelected(NodeAttribute::fromNode($value->setParent($this)));
        return $this;
    }
}
