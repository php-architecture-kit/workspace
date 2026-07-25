<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Ver5;

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

class ObjectNode extends SequenceNode
{
    public StructureAttribute $beginObject { get => $this->attributes[0]; }

    /** @var GroupAttribute<TrailingWsNode|TrailingCommentNode|InlineWsNode|EmptyLineNode|LeadingWsNode> */
    public GroupAttribute $trivia0 { get => $this->attributes[1]; }

    /** @var SequenceAttribute<NodeAttribute<MemberNode>|GroupAttribute|StructureAttribute> */
    public SequenceAttribute $members { get => $this->attributes[2]; }

    /** @var GroupAttribute<LeadingWsNode|EmptyLineNode|TrailingWsNode|InlineWsNode> */
    public GroupAttribute $trivia1 { get => $this->attributes[3]; }
    public StructureAttribute $endObject { get => $this->attributes[4]; }

    public static function create(): self
    {
        $node = new self(
            name: 'object',
            origin: NodeOrigin::Sequence,
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
        $node->withMembersValidation();

        return $node;
    }

    public function withMembersValidation(): self
    {
        $this->members->withValidSequence(
            self::membersValidity(),
            [
                'trivia0' => static fn() => new GroupAttribute('trivia0', []),
                'comma' => static fn() => new StructureAttribute(true, 'comma', ','),
                'trivia1' => static fn() => new GroupAttribute('trivia1', []),
                'trivia2' => static fn() => new GroupAttribute('trivia2', []),
                'trivia' => static fn() => new GroupAttribute('trivia', []),
                'trailingComma' => static fn() => new StructureAttribute(true, 'trailingComma', ','),
            ],
        );
        return $this;
    }

    public function addMember(MemberNode $node): self
    {
        $this->members->addUnit(new NodeAttribute('member', $node->setParent($this)));
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

    private static function membersValidity(): NestedSequence
    {
        return NestedSequence::fromString('?(leadingComment|trailingComment|whitespace*[trivia0] member (leadingComment|trailingComment|whitespace*[trivia0] comma trailingComment|trailingWs|inlineWs*[trivia1] leadingComment|trailingComment|whitespace*[trivia2] member)* ?(leadingComment|trailingComment|whitespace*[trivia] comma[trailingComma]) trailingComment|trailingWs|inlineWs*[trivia1])[members]');
    }
}
