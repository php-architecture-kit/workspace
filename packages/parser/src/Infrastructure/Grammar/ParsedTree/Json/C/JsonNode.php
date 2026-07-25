<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\C;

use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\GroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\NodeAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeOrigin;
use PhpArchitecture\Parser\Foundation\Parsing\Model\SequenceNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\EmptyLineNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\InlineWsNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\LeadingWsNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\TrailingWsNode;

class JsonNode extends SequenceNode
{
    /** @var GroupAttribute<LeadingCommentNode|EmptyLineNode|TrailingWsNode|LeadingWsNode|InlineWsNode> */
    public GroupAttribute $trivia0 { get => $this->attributes[0]; }

    /** @var NodeAttribute<ObjectNode|ArrayNode|PrimitiveNode> */
    public NodeAttribute $value { get => $this->attributes[1]; }

    /** @var GroupAttribute<TrailingWsNode|EmptyLineNode|LeadingCommentNode|LeadingWsNode|InlineWsNode> */
    public GroupAttribute $trivia1 { get => $this->attributes[2]; }

    public static function create(ObjectNode|ArrayNode|PrimitiveNode $value): self
    {
        $node = new self(
            name: 'json',
            origin: NodeOrigin::Sequence,
            attributes: [
                new GroupAttribute('trivia0', []),
                NodeAttribute::fromNode($value),
                new GroupAttribute('trivia1', []),
            ],
            parent: null,
        );
        $value->setParent($node);

        return $node;
    }

    public function getNodeValue(): ObjectNode|ArrayNode|PrimitiveNode
    {
        /** @var ObjectNode|ArrayNode|PrimitiveNode $node */
        $node = $this->value->node;
        return $node;
    }

    public function setNodeValue(ObjectNode|ArrayNode|PrimitiveNode $value): self
    {
        $this->attributes[1] = NodeAttribute::fromNode($value->setParent($this));
        return $this;
    }
}
