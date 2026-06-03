<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Rfc8259;

use PhpArchitecture\Parser\Foundation\Matching\Model\NestedSequence;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeAttributeInterface;
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

class ObjectNode extends Node
{
    public StructureAttribute $beginObject { get => $this->attributes[0]; }

    /** @var GroupAttribute<TrailingWsNode|EmptyLineNode|InlineWsNode|LeadingWsNode> */
    public GroupAttribute $trivia0 { get => $this->attributes[1]; }

    /** @var GroupedAttribute<MemberNode|StructureAttribute|TrailingWsNode|EmptyLineNode|InlineWsNode|LeadingWsNode> */
    public GroupedAttribute $members { get => $this->attributes[2]; }

    /** @var GroupAttribute<TrailingWsNode|EmptyLineNode|InlineWsNode|LeadingWsNode> */
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

    /**
     * Enables structural validation and auto-insertion of structural attributes
     * (trivia0, comma, trivia1) when adding members via addMemberToMembers().
     *
     * Pass the compiled root sequence of the 'object' region, or a pre-built cursor
     * positioned at the 'members' anchor.
     */
    public function withMembersValidation(NestedSequence|SequenceValidityCursor $sequence): self
    {
        $this->members->withValidSequence($sequence, [
            'trivia0' => static fn() => new GroupAttribute('trivia0', []),
            'comma'   => static fn() => new StructureAttribute(true, 'comma', ','),
            'trivia1' => static fn() => new GroupAttribute('trivia1', []),
        ]);

        return $this;
    }

    /**
     * Adds a member to the members group, auto-inserting comma and trivia when needed.
     * Requires withMembersValidation() to have been called first.
     */
    public function addMemberToMembers(MemberNode $member): self
    {
        $this->members->addUnit(NodeAttribute::fromNode($member->setParent($this)));

        return $this;
    }

    /**
     * Removes the member at the given logical index (0-based), along with its
     * structural attributes (trivia, comma).
     */
    public function removeMemberFromMembersByIndex(int $index): self
    {
        $this->members->removeUnit($index);

        return $this;
    }

    /**
     * Returns all attributes making up the member unit at the given index
     * (the MemberNode attribute together with its trivia and comma).
     * Requires withMembersValidation() to have been called first.
     *
     * @return NodeAttributeInterface[]
     */
    public function getMemberUnitFromMembers(int $index): array
    {
        return $this->members->getUnit($index);
    }

    /**
     * Returns all MemberNode instances from the members group, in order.
     * Works without withMembersValidation() by filtering by attribute name.
     *
     * @return MemberNode[]
     */
    public function getMembersFromMembers(): array
    {
        $result = [];
        foreach ($this->members->attributes as $attr) {
            if ($attr instanceof NodeAttribute && $attr->getName() === 'member') {
                /** @var MemberNode $memberNode */
                $memberNode = $attr->node;
                $result[] = $memberNode;
            }
        }
        return $result;
    }
}
