<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\TreeSchema\Model\Json\C;

use PhpArchitecture\Parser\Foundation\Matching\Model\NestedSequence;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeAttributeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\ChoiceAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\GroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\GroupedAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\NodeAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\SequenceValidityCursor;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\StructureAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Node;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\EmptyLineNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\InlineWsNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\LeadingWsNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\TrailingWsNode;

class ArrayNode extends Node
{
    public StructureAttribute $beginArray { get => $this->attributes[0]; }

    /** @var GroupAttribute<TrailingWsNode|LeadingWsNode|BlockCommentNode|EmptyLineNode|InlineWsNode> */
    public GroupAttribute $trivia0 { get => $this->attributes[1]; }

    /** @var GroupedAttribute<ChoiceAttribute<PrimitiveNode|ObjectNode|ArrayNode>|GroupAttribute|StructureAttribute|PrimitiveNode|ObjectNode|ArrayNode> */
    public GroupedAttribute $items { get => $this->attributes[2]; }

    /** @var GroupAttribute<TrailingWsNode|InlineWsNode|LeadingWsNode|EmptyLineNode> */
    public GroupAttribute $trivia1 { get => $this->attributes[3]; }
    public StructureAttribute $endArray { get => $this->attributes[4]; }

    public static function create(): self
    {
        $node = new self(
            name: 'array',
            attributes: [
            new StructureAttribute(true, 'beginArray', '['),
            new GroupAttribute('trivia0', []),
            new GroupedAttribute('items', null, []),
            new GroupAttribute('trivia1', []),
            new StructureAttribute(true, 'endArray', ']'),
            ],
            parent: null,
        );
        $node->items->withParent($node);

        return $node;
    }

    public function withItemsValidation(NestedSequence|SequenceValidityCursor $sequence): self
    {
        $this->items->withValidSequence($sequence, [
            'trivia0' => static fn() => new GroupAttribute('trivia0', []),
            'comma' => static fn() => new StructureAttribute(true, 'comma', ','),
            'trivia1' => static fn() => new GroupAttribute('trivia1', []),
        ]);
        return $this;
    }

    public function addItem(PrimitiveNode|ObjectNode|ArrayNode $node): self
    {
        $this->items->addUnit(new ChoiceAttribute(
            'item',
            ['array', 'object', 'primitive'],
            NodeAttribute::fromNode($node->setParent($this)),
        ));
        return $this;
    }

    public function removeItemByIndex(int $index): self
    {
        $this->items->removeUnit($index);
        return $this;
    }

    /** @return NodeAttributeInterface[] */
    public function getItemUnit(int $index): array
    {
        return $this->items->getUnit($index);
    }

    /** @return array<PrimitiveNode|ObjectNode|ArrayNode> */
    public function getItems(): array
    {
        $result = [];
        foreach ($this->items->attributes as $attr) {
            if ($attr instanceof ChoiceAttribute && $attr->getName() === 'item') {
                if ($attr->selected instanceof NodeAttribute) {
                    $result[] = $attr->selected->node;
                }
            }
        }
        return $result;
    }
}
