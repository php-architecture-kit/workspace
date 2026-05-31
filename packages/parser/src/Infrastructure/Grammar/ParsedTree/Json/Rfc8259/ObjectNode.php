<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Rfc8259;

use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\GroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\GroupedAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\StructureAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Node;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\EmptyLineNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\LeadingWsNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\TrailingWsNode;

class ObjectNode extends Node
{
    public StructureAttribute $beginObject { get => $this->attributes[0]; }

    /** @var GroupAttribute<TrailingWsNode|EmptyLineNode|LeadingWsNode> */
    public GroupAttribute $trivia0 { get => $this->attributes[1]; }

    /** @var GroupedAttribute<MemberNode|StructureAttribute|TrailingWsNode|EmptyLineNode|LeadingWsNode> */
    public GroupedAttribute $members { get => $this->attributes[2]; }

    /** @var GroupAttribute<TrailingWsNode|EmptyLineNode|LeadingWsNode> */
    public GroupAttribute $trivia1 { get => $this->attributes[3]; }

    public StructureAttribute $endObject { get => $this->attributes[4]; }

    public static function create(): self
    {
        $node = new self(
            name: 'object',
            attributes: [
                new StructureAttribute(true, 'beginObject', '{'),
                new GroupAttribute('trivia0', []),
                new GroupedAttribute('members', null, []),
                new GroupAttribute('trivia1', []),
                new StructureAttribute(true, 'endObject', '}'),
            ],
            parent: null,
        );

        $node->members->withParent($node);

        return $node;
    }
}
