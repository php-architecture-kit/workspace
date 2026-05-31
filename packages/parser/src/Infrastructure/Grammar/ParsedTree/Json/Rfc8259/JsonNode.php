<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Rfc8259;

use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\Placement;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\ChoiceAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\GroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\NodeAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Node;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\LeadingWsNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\TrailingWsNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\EmptyLineNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\InlineWsNode;

class JsonNode extends Node
{
    /** @var GroupAttribute<LeadingWsNode|TrailingWsNode|EmptyLineNode|InlineWsNode> */
    public GroupAttribute $trivia0 { get => $this->attributes[0]; }

    /** @var ChoiceAttribute<ObjectNode|ArrayNode|PrimitiveNode> */
    public ChoiceAttribute $value { get => $this->attributes[1]; }

    /** @var GroupAttribute<LeadingWsNode|TrailingWsNode|EmptyLineNode|InlineWsNode> */
    public GroupAttribute $trivia1 { get => $this->attributes[2]; }

    public static function create(): self
    {
        $node = new self(
            name: 'json',
            attributes: [
                new GroupAttribute('trivia0', []),
                new ChoiceAttribute('value', ['object', 'array', 'primitive'], null),
                new GroupAttribute('trivia1', [])
            ],
            parent: null,
        );

        return $node;
    }

    public function addNodeToTrivia0(EmptyLineNode|LeadingWsNode $node, Placement $placement = Placement::After, int $offset = -1): self
    {
        $this->trivia0->addNode($node->setParent($this), $placement, $offset);

        return $this;
    }

    /**
     * @return array<EmptyLineNode|LeadingWsNode>
     */
    public function getNodesFromTrivia0(?callable $filter = null): array
    {
        return $this->trivia0->getNodes($filter);
    }

    public function removeNodeFromTrivia0ByOffset(int $offset): self
    {
        $this->trivia0->removeNodeByOffset($offset);

        return $this;
    }

    /**
     * @param callable(NodeInterface):bool $filter true - stay, false - remove
     */
    public function removeNodeFromTrivia0ByFilter(callable $filter): self
    {
        $this->trivia0->removeNodeByFilter($filter);

        return $this;
    }

    // metody getNode{name} i setNode{name} ponieważ ChoiceAttribute zawiera klasy node'ów na liście choices.
    // choice attribute z cardinality = 1, tak jak w tym wypadku, oznacza że nie ma metody removeNode{name}

    public function getNodeValue(): null|ObjectNode|ArrayNode|PrimitiveNode
    {
        /** @var ?NodeAttribute $attribute */
        $attribute = $this->value->selected;

        return $attribute?->node;
    }

    public function setNodeValue(ObjectNode|ArrayNode|PrimitiveNode $value): self
    {
        $this->value->setSelected(NodeAttribute::fromNode($value->setParent($this)));

        return $this;
    }

    public function addNodeToTrivia1(EmptyLineNode|LeadingWsNode $node, Placement $placement = Placement::After, int $offset = -1): self
    {
        $this->trivia1->addNode($node->setParent($this), $placement, $offset);

        return $this;
    }

    /**
     * @return array<EmptyLineNode|LeadingWsNode>
     */
    public function getNodesFromTrivia1(?callable $filter = null): array
    {
        return $this->trivia1->getNodes($filter);
    }

    public function removeNodeFromTrivia1ByOffset(int $offset): self
    {
        $this->trivia1->removeNodeByOffset($offset);

        return $this;
    }

    /**
     * @param callable(NodeInterface):bool $filter true - stay, false - remove
     */
    public function removeNodeFromTrivia1ByFilter(callable $filter): self
    {
        $this->trivia1->removeNodeByFilter($filter);

        return $this;
    }
}
