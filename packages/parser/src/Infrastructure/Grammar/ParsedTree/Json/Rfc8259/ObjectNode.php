<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Rfc8259;

use PhpArchitecture\Parser\Foundation\Matching\Model\NestedSequence;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeAttributeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\GroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\NodeAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\SequenceAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\SequenceValidityCursor;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\StructureAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Node;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\EmptyLineNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\InlineWsNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\LeadingWsNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\TrailingWsNode;

class ObjectNode extends Node
{
    public StructureAttribute $beginObject { get => $this->attributes[0]; }

    /** @var GroupAttribute<TrailingWsNode|InlineWsNode|LeadingWsNode|EmptyLineNode> */
    public GroupAttribute $trivia0 { get => $this->attributes[1]; }

    /** @var SequenceAttribute<NodeAttribute<MemberNode>|GroupAttribute|StructureAttribute|MemberNode> */
    public SequenceAttribute $members { get => $this->attributes[2]; }

    /** @var GroupAttribute<TrailingWsNode|LeadingWsNode|EmptyLineNode|InlineWsNode> */
    public GroupAttribute $trivia1 { get => $this->attributes[3]; }

    public StructureAttribute $endObject { get => $this->attributes[4]; }

    public static function create(): self
    {
        $node = new self(
            name: 'object',
            attributes: [
            new StructureAttribute(true, 'beginObject', '{'),
            new GroupAttribute('trivia0', []),
            new SequenceAttribute('members', null, []),
            new GroupAttribute('trivia1', []),
            new StructureAttribute(true, 'endObject', '}'),
            ],
            parent: null,
        );
        $node->members->withParent($node);

        return $node;
    }

    public function withMembersValidation(NestedSequence|SequenceValidityCursor $sequence): self
    {
        $this->members->withValidSequence($sequence, [
            'trivia0' => static fn() => new GroupAttribute('trivia0', []),
            'comma' => static fn() => new StructureAttribute(true, 'comma', ','),
            'trivia1' => static fn() => new GroupAttribute('trivia1', []),
        ]);
        return $this;
    }

    public function addMember(MemberNode $node): self
    {
        $this->members->addUnit(NodeAttribute::fromNode($node->setParent($this)));
        return $this;
    }

    public function removeMemberByIndex(int $index): self
    {
        $this->members->removeUnit($index);
        return $this;
    }

    /** @return NodeAttributeInterface[] */
    public function getMemberUnit(int $index): array
    {
        return $this->members->getUnit($index);
    }

    /** @return array<MemberNode> */
    public function getMembers(): array
    {
        $result = [];
        foreach ($this->members->attributes as $attr) {
            if ($attr instanceof NodeAttribute && $attr->getName() === 'member') {
                $result[] = $attr->node;
            }
        }
        return $result;
    }
}
