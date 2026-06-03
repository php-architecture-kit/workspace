<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Rfc8259;

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

    /** @var GroupAttribute<LeadingWsNode|TrailingWsNode|EmptyLineNode|InlineWsNode> */
    public GroupAttribute $trivia0 { get => $this->attributes[1]; }

    /** @var GroupedAttribute<ChoiceAttribute<PrimitiveNode|ObjectNode|ArrayNode>|StructureAttribute|TrailingWsNode|EmptyLineNode|InlineWsNode|LeadingWsNode> */
    public GroupedAttribute $items { get => $this->attributes[2]; }

    /** @var GroupAttribute<LeadingWsNode|TrailingWsNode|EmptyLineNode|InlineWsNode> */
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

    /**
     * Enables structural validation and auto-insertion of structural attributes
     * (trivia0, comma, trivia1) when adding items via addItemToItems().
     *
     * Pass the compiled root sequence of the 'array' region, or a pre-built cursor
     * positioned at the 'items' anchor.
     */
    public function withItemsValidation(NestedSequence|SequenceValidityCursor $sequence): self
    {
        $this->items->withValidSequence($sequence, [
            'trivia0' => static fn() => new GroupAttribute('trivia0', []),
            'comma'   => static fn() => new StructureAttribute(true, 'comma', ','),
            'trivia1' => static fn() => new GroupAttribute('trivia1', []),
        ]);

        return $this;
    }

    /**
     * Adds an item to the items group, auto-inserting comma and trivia when needed.
     * Requires withItemsValidation() to have been called first.
     */
    public function addItemToItems(PrimitiveNode|ObjectNode|ArrayNode $node): self
    {
        $this->items->addUnit(new ChoiceAttribute(
            'item',
            ['array', 'object', 'primitive'],
            NodeAttribute::fromNode($node->setParent($this)),
        ));

        return $this;
    }

    /**
     * Removes the item at the given logical index (0-based), along with its
     * structural attributes (trivia, comma).
     */
    public function removeItemFromItemsByIndex(int $index): self
    {
        $this->items->removeUnit($index);

        return $this;
    }

    /**
     * Returns all attributes making up the item unit at the given index
     * (the item ChoiceAttribute together with its trivia and comma).
     * Requires withItemsValidation() to have been called first.
     *
     * @return NodeAttributeInterface[]
     */
    public function getItemUnitFromItems(int $index): array
    {
        return $this->items->getUnit($index);
    }

    /**
     * Returns all item nodes from the items group, in order.
     * Works without withItemsValidation() by filtering by attribute name.
     *
     * @return array<PrimitiveNode|ObjectNode|ArrayNode>
     */
    public function getItemsFromItems(): array
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
