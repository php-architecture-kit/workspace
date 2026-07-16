<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\C;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Sequence\NestedSequence;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeAttributeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\Placement;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\SequenceUnitTrivia;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\TriviaInsertionContext;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\TriviaPolicyRegistry;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\UnitTriviaPosition;
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

    /** @var GroupAttribute<TrailingWsNode|BlockCommentNode|LineCommentNode|InlineWsNode|EmptyLineNode|LeadingWsNode> */
    public GroupAttribute $trivia0 { get => $this->attributes[1]; }

    /** @var SequenceAttribute<NodeAttribute<MemberNode>|GroupAttribute|StructureAttribute> */
    public SequenceAttribute $members { get => $this->attributes[2]; }

    /** @var GroupAttribute<LeadingWsNode|LineCommentNode|TrailingWsNode|EmptyLineNode|InlineWsNode> */
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

    /**
     * Builds the right node for $text via the TriviaInsertionPolicy registered
     * for this class (TriviaPolicyRegistry) — this slot accepts more than one
     * alternative node type, so the policy decides which one is safe here.
     */
    public function insertIntoTrivia0(string $text, Placement $placement = Placement::After, int $offset = -1): self
    {
        $node = TriviaPolicyRegistry::resolve(static::class)->resolve($text, new TriviaInsertionContext($this->trivia0, $placement, $offset));
        $this->trivia0->addNode($node->setParent($this), $placement, $offset);
        return $this;
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
        return NestedSequence::fromString('?(lineComment|blockComment|whitespace*[trivia0] member (lineComment|blockComment|whitespace*[trivia0] comma trailingWs|inlineWs|lineComment|blockComment*[trivia1] lineComment|blockComment|whitespace*[trivia2] member)* trailingWs|inlineWs|lineComment|blockComment*[trivia1])[members]');
    }

    /**
     * Builds the right node for $text via the TriviaInsertionPolicy registered
     * for this class, then inserts it into the trivia group at $position within
     * the $unitIndex-th members unit (SequenceUnitTrivia resolves $position
     * relative to that unit's own content — these groups repeat once per unit,
     * so unlike insertInto{Trivia}(), there is no single fixed property here).
     */
    public function insertIntoMembersTrivia(int $unitIndex, UnitTriviaPosition $position, string $text, Placement $placement = Placement::After, int $offset = -1): self
    {
        $group = SequenceUnitTrivia::locate($this->members->getUnit($unitIndex), $position);
        $node = TriviaPolicyRegistry::resolve(static::class)->resolve($text, new TriviaInsertionContext($group, $placement, $offset));
        $group->addNode($node->setParent($this), $placement, $offset);
        return $this;
    }

    /**
     * Builds the right node for $text via the TriviaInsertionPolicy registered
     * for this class, then inserts it right after the structural attribute
     * named $structuralName (e.g. 'comma') within the $unitIndex-th members unit.
     */
    public function insertIntoMembersTriviaAfterStructural(int $unitIndex, string $structuralName, string $text, Placement $placement = Placement::After, int $offset = -1): self
    {
        $group = SequenceUnitTrivia::locateAfterStructural($this->members->getUnit($unitIndex), $structuralName);
        $node = TriviaPolicyRegistry::resolve(static::class)->resolve($text, new TriviaInsertionContext($group, $placement, $offset));
        $group->addNode($node->setParent($this), $placement, $offset);
        return $this;
    }

    /**
     * Builds the right node for $text via the TriviaInsertionPolicy registered
     * for this class (TriviaPolicyRegistry) — this slot accepts more than one
     * alternative node type, so the policy decides which one is safe here.
     */
    public function insertIntoTrivia1(string $text, Placement $placement = Placement::After, int $offset = -1): self
    {
        $node = TriviaPolicyRegistry::resolve(static::class)->resolve($text, new TriviaInsertionContext($this->trivia1, $placement, $offset));
        $this->trivia1->addNode($node->setParent($this), $placement, $offset);
        return $this;
    }
}
