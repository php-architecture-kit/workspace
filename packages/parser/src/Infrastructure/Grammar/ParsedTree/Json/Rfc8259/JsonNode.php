<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Rfc8259;

use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\Placement;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\GroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\NodeAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Node;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\EmptyLineNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\InlineWsNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\LeadingWsNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\TrailingWsNode;

class JsonNode extends Node
{
    /** @var GroupAttribute<EmptyLineNode|LeadingWsNode|TrailingWsNode|InlineWsNode> */
    public GroupAttribute $trivia0 { get => $this->attributes[0]; }

    /** @var NodeAttribute<ObjectNode|ArrayNode> */
    public NodeAttribute $value { get => $this->attributes[1]; }

    /** @var GroupAttribute<TrailingWsNode|EmptyLineNode|LeadingWsNode|InlineWsNode> */
    public GroupAttribute $trivia1 { get => $this->attributes[2]; }

    public static function create(ObjectNode $objectNode): self
    {
        return new self(
            name: 'json',
            attributes: [
            new GroupAttribute('trivia0', []),
            NodeAttribute::fromNode($objectNode),
            new GroupAttribute('trivia1', []),
            ],
            parent: null,
        );
    }

    public function addNodeToTrivia0(EmptyLineNode|LeadingWsNode|TrailingWsNode|InlineWsNode $node, Placement $placement = Placement::After, int $offset = -1): self
    {
        $this->trivia0->addNode($node->setParent($this), $placement, $offset);
        return $this;
    }

    /** @return array<EmptyLineNode|LeadingWsNode|TrailingWsNode|InlineWsNode> */
    public function getNodesFromTrivia0(?callable $filter = null): array
    {
        return $this->trivia0->getNodes($filter);
    }

    public function removeNodeFromTrivia0ByOffset(int $offset): self
    {
        $this->trivia0->removeNodeByOffset($offset);
        return $this;
    }

    /** @param callable(NodeInterface):bool $filter true - stay, false - remove */
    public function removeNodeFromTrivia0ByFilter(callable $filter): self
    {
        $this->trivia0->removeNodeByFilter($filter);
        return $this;
    }

    public function getNodeValue(): ObjectNode|ArrayNode
    {
        /** @var NodeAttribute $attribute */
        $attribute = $this->value;
        return $attribute->node;
    }

    public function setNodeValue(ObjectNode|ArrayNode $value): self
    {
        $this->value = NodeAttribute::fromNode($value->setParent($this));
        return $this;
    }

    public function addNodeToTrivia1(TrailingWsNode|EmptyLineNode|LeadingWsNode|InlineWsNode $node, Placement $placement = Placement::After, int $offset = -1): self
    {
        $this->trivia1->addNode($node->setParent($this), $placement, $offset);
        return $this;
    }

    /** @return array<TrailingWsNode|EmptyLineNode|LeadingWsNode|InlineWsNode> */
    public function getNodesFromTrivia1(?callable $filter = null): array
    {
        return $this->trivia1->getNodes($filter);
    }

    public function removeNodeFromTrivia1ByOffset(int $offset): self
    {
        $this->trivia1->removeNodeByOffset($offset);
        return $this;
    }

    /** @param callable(NodeInterface):bool $filter true - stay, false - remove */
    public function removeNodeFromTrivia1ByFilter(callable $filter): self
    {
        $this->trivia1->removeNodeByFilter($filter);
        return $this;
    }
}
