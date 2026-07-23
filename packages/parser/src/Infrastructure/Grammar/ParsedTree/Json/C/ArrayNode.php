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

class ArrayNode extends SequenceNode
{
    public StructureAttribute $beginArray { get => $this->attributes[0]; }

    /** @var GroupAttribute<TrailingWsNode|BlockCommentNode|LineCommentNode|EmptyLineNode|LeadingWsNode|InlineWsNode> */
    public GroupAttribute $trivia0 { get => $this->attributes[1]; }

    /** @var SequenceAttribute<NodeAttribute<PrimitiveNode|ObjectNode|ArrayNode>|GroupAttribute|StructureAttribute> */
    public SequenceAttribute $items { get => $this->attributes[2]; }

    /** @var GroupAttribute<LeadingWsNode|EmptyLineNode|TrailingWsNode|InlineWsNode> */
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
        return NestedSequence::fromString('?(lineComment|blockComment|whitespace*[trivia0] array|object|primitive[item] (lineComment|blockComment|whitespace*[trivia0] comma trailingWs|inlineWs|lineComment|blockComment*[trivia1] lineComment|blockComment|whitespace*[trivia2] array|object|primitive[item])* trailingWs|inlineWs|lineComment|blockComment*[trivia1])[items]');
    }

    /**
     * Builds the right node for $text via the TriviaInsertionPolicy registered
     * for this class, then inserts it into the trivia group at $position within
     * the $unitIndex-th items unit (SequenceUnitTrivia resolves $position
     * relative to that unit's own content — these groups repeat once per unit,
     * so unlike insertInto{Trivia}(), there is no single fixed property here).
     */
    public function insertIntoItemsTrivia(int $unitIndex, UnitTriviaPosition $position, string $text, Placement $placement = Placement::After, int $offset = -1): self
    {
        $group = SequenceUnitTrivia::locate($this->items->getUnit($unitIndex), $position);
        $node = TriviaPolicyRegistry::resolve(static::class)->resolve($text, new TriviaInsertionContext($group, $placement, $offset));
        $group->addNode($node->setParent($this), $placement, $offset);
        return $this;
    }

    /**
     * Builds the right node for $text via the TriviaInsertionPolicy registered
     * for this class, then inserts it right after the structural attribute
     * named $structuralName (e.g. 'comma') within the $unitIndex-th items unit.
     */
    public function insertIntoItemsTriviaAfterStructural(int $unitIndex, string $structuralName, string $text, Placement $placement = Placement::After, int $offset = -1): self
    {
        $group = SequenceUnitTrivia::locateAfterStructural($this->items->getUnit($unitIndex), $structuralName);
        $node = TriviaPolicyRegistry::resolve(static::class)->resolve($text, new TriviaInsertionContext($group, $placement, $offset));
        $group->addNode($node->setParent($this), $placement, $offset);
        return $this;
    }
}
