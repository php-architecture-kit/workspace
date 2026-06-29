<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Ver5;

use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\NodeAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeOrigin;
use PhpArchitecture\Parser\Foundation\Parsing\Model\SequenceNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Comment\MultiLineNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Comment\SingleLineNode;

class BlockCommentNode extends SequenceNode
{
    /** @var NodeAttribute<SingleLineNode|MultiLineNode> */
    public NodeAttribute $variant { get => $this->attributes[0]; }

    public static function create(SingleLineNode|MultiLineNode $variant): self
    {
        $node = new self(
            name: 'blockComment',
            origin: NodeOrigin::Sequence,
            attributes: [
                NodeAttribute::fromNode($variant),
            ],
            parent: null,
        );
        $variant->setParent($node);

        return $node;
    }

    public function getNodeVariant(): SingleLineNode|MultiLineNode
    {
        /** @var SingleLineNode|MultiLineNode $node */
        $node = $this->variant->node;
        return $node;
    }

    public function setNodeVariant(SingleLineNode|MultiLineNode $value): self
    {
        $this->attributes[0] = NodeAttribute::fromNode($value->setParent($this));
        return $this;
    }
}
