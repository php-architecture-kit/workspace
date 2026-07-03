<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Git\Gitmodules;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Sequence\NestedSequence;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeAttributeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\GroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\NodeAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\SequenceAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeOrigin;
use PhpArchitecture\Parser\Foundation\Parsing\Model\SequenceNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\EmptyLineNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\InlineWsNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\LeadingWsNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\TrailingWsNode;

class SectionNode extends SequenceNode
{
    /** @var NodeAttribute<QuotedSectionHeaderNode|DottedSectionHeaderNode|BareSectionHeaderNode> */
    public NodeAttribute $header { get => $this->attributes[0]; }

    /** @var GroupAttribute<TrailingWsNode|LeadingWsNode|EmptyLineNode|InlineWsNode> */
    public GroupAttribute $trivia0 { get => $this->attributes[1]; }

    /** @var SequenceAttribute<GroupAttribute> */
    public SequenceAttribute $entries { get => $this->attributes[2]; }

    /** @var GroupAttribute<EmptyLineNode|LeadingWsNode|TrailingWsNode|InlineWsNode> */
    public GroupAttribute $trivia1 { get => $this->attributes[3]; }

    public static function create(QuotedSectionHeaderNode|DottedSectionHeaderNode|BareSectionHeaderNode $header): self
    {
        $node = new self(
            name: 'section',
            origin: NodeOrigin::Sequence,
            attributes: [
                NodeAttribute::fromNode($header),
                new GroupAttribute('trivia0', []),
                new SequenceAttribute('entries', null, []),
                new GroupAttribute('trivia1', []),
            ],
            parent: null,
        );
        $header->setParent($node);
        $node->entries->withParent($node);

        return $node;
    }

    public function getNodeHeader(): QuotedSectionHeaderNode|DottedSectionHeaderNode|BareSectionHeaderNode
    {
        /** @var QuotedSectionHeaderNode|DottedSectionHeaderNode|BareSectionHeaderNode $node */
        $node = $this->header->node;
        return $node;
    }

    public function setNodeHeader(QuotedSectionHeaderNode|DottedSectionHeaderNode|BareSectionHeaderNode $value): self
    {
        $this->attributes[0] = NodeAttribute::fromNode($value->setParent($this));
        return $this;
    }

    public function addEntries(NodeNode $nodeNode): self
    {
        $this->entries->addUnit(new NodeAttribute('entries', $nodeNode->setParent($this)));
        return $this;
    }

    public function removeEntriesByIndex(int $index): self
    {
        $this->entries->removeUnit($index);
        return $this;
    }

    /** @return NodeAttributeInterface[] */
    public function getEntriesUnit(int $index): array
    {
        return $this->entries->getUnit($index);
    }

    /** @return NodeNode[] */
    public function getEntriess(): array
    {
        $result = [];
        foreach ($this->entries->attributes as $attr) {
            if ($attr instanceof NodeAttribute && $attr->getName() === 'entries') {
                /** @var NodeNode $node */
                $node = $attr->node;
                $result[] = $node;
            }
        }
        return $result;
    }
}
