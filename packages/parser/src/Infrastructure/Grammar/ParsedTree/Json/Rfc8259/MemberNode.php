<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Rfc8259;

use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\ChoiceAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\GroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\NodeAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\RawRegionAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\StructureAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Node;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\InlineWsNode;

class MemberNode extends Node
{
    public RawRegionAttribute $identifier { get => $this->attributes[0]; }

    public GroupAttribute $trivia0 { get => $this->attributes[1]; }

    public StructureAttribute $colon { get => $this->attributes[2]; }

    /** @var GroupAttribute<InlineWsNode> */
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

    public function getNodeValue(): null|ObjectNode|ArrayNode|PrimitiveNode
    {
        /** @var ?NodeAttribute $attribute */
        $attribute = $this->value->selected;

        return $attribute?->node;
    }

    public function setNodeValue(ObjectNode|ArrayNode|PrimitiveNode $value): self
    {
        $this->value->setSelected(NodeAttribute::fromNode($value->setParent($this)));

        return $this;
    }
}
