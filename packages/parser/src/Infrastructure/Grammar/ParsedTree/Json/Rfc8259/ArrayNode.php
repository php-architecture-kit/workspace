<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Rfc8259;

use PhpArchitecture\Parser\Foundation\Parsing\Contract\Placement;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\ChoiceAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\GroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\GroupedAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\StructureAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Node;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\EmptyLineNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\LeadingWsNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\TrailingWsNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\InlineWsNode;

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

    public function addNodeToItems(PrimitiveNode|ObjectNode|ArrayNode|TrailingWsNode|EmptyLineNode|InlineWsNode|LeadingWsNode $node, Placement $placement = Placement::After, int $offset = -1): self
    {
        
    }
}
