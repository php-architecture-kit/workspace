<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Rfc8259;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Sequence\NestedSequence;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeAttributeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\GroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\NodeAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\SequenceAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Structure\StructureAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeOrigin;
use PhpArchitecture\Parser\Foundation\Parsing\Model\SequenceNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\EmptyLineNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\InlineWsNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\LeadingWsNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\TrailingWsNode;

class ArrayNode extends SequenceNode
{
    public StructureAttribute $beginArray { get => $this->attributes[0]; }

    /** @var GroupAttribute<TrailingWsNode|LeadingWsNode|InlineWsNode|EmptyLineNode> */
    public GroupAttribute $trivia0 { get => $this->attributes[1]; }

    /** @var SequenceAttribute<NodeAttribute<PrimitiveNode|ObjectNode|ArrayNode>|GroupAttribute|StructureAttribute> */
    public SequenceAttribute $items { get => $this->attributes[2]; }

    public GroupAttribute $trivia1 { get => $this->attributes[3]; }

    public StructureAttribute $endArray { get => $this->attributes[4]; }

    public static function create(): self
    {
        $node = new self(
            name: 'array',
            origin: NodeOrigin::Sequence,
            attributes: [
                new StructureAttribute(true, 'beginArray', '['),
                new GroupAttribute('trivia0', []),
                new SequenceAttribute('items', null, []),
                new GroupAttribute('trivia1', []),
                new StructureAttribute(true, 'endArray', ']'),
            ],
            parent: null,
        );
        $node->items->withParent($node);
        $node->withItemsValidation();

        return $node;
    }

    public function withItemsValidation(): self
    {
        $this->items->withValidSequence(
            self::itemsValidity(),
            [
                'trivia0' => static fn() => new GroupAttribute('trivia0', []),
                'trivia1' => static fn() => new GroupAttribute('trivia1', []),
                'comma' => static fn() => new StructureAttribute(true, 'comma', ','),
                'trivia2' => static fn() => new GroupAttribute('trivia2', []),
            ],
        );
        return $this;
    }

    public function addItem(PrimitiveNode|ObjectNode|ArrayNode $node): self
    {
        $this->items->addUnit(new NodeAttribute('item', $node->setParent($this)));
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
            if ($attr instanceof NodeAttribute && $attr->getName() === 'item') {
                $result[] = $attr->node;
            }
        }
        return $result;
    }

    private static function itemsValidity(): NestedSequence
    {
        return NestedSequence::fromString('?(whitespace*[trivia0] array|object|primitive[item] (whitespace*[trivia0] comma whitespace*[trivia1] whitespace*[trivia2] array|object|primitive[item])* whitespace*[trivia1])[items]');
    }
}
