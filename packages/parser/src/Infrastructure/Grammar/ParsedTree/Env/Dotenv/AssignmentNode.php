<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Env\Dotenv;

use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\NodeAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawContentAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawGroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Structure\StructureAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeOrigin;
use PhpArchitecture\Parser\Foundation\Parsing\Model\SequenceNode;

class AssignmentNode extends SequenceNode
{
    public RawContentAttribute $identifier { get => $this->attributes[0]; }
    public RawGroupAttribute $trivia0 { get => $this->attributes[1]; }
    public StructureAttribute $assign { get => $this->attributes[2]; }
    public RawGroupAttribute $trivia1 { get => $this->attributes[3]; }

    /** @var NodeAttribute<ValueNode> */
    public NodeAttribute $value { get => $this->attributes[4]; }
    public RawGroupAttribute $trivia2 { get => $this->attributes[5]; }

    public static function create(string $identifier, ValueNode $value): self
    {
        $node = new self(
            name: 'assignment',
            origin: NodeOrigin::Sequence,
            attributes: [
                new RawContentAttribute($identifier),
                new StructureAttribute(true, 'assign', '='),
                NodeAttribute::fromNode($value),
            ],
            parent: null,
        );
        $value->setParent($node);

        return $node;
    }

    public function getRawIdentifier(): string
    {
        return $this->identifier->content;
    }

    public function setRawIdentifier(string $value): self
    {
        $this->identifier->content = $value;
        return $this;
    }

    public function getNodeValue(): ValueNode
    {
        /** @var ValueNode $node */
        $node = $this->value->node;
        return $node;
    }

    public function setNodeValue(ValueNode $value): self
    {
        $this->attributes[4] = NodeAttribute::fromNode($value->setParent($this));
        return $this;
    }
}
