<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\C;

use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\NodeAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\OptionalRawAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawGroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawRegionAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeOrigin;
use PhpArchitecture\Parser\Foundation\Parsing\Model\SequenceNode;

class LeadingCommentNode extends SequenceNode
{
    public OptionalRawAttribute $leadingWs { get => $this->attributes[0]; }

    /** @var NodeAttribute<BlockCommentNode|LineCommentNode> */
    public NodeAttribute $lineComment { get => $this->attributes[1]; }

    public RawGroupAttribute $trailingWs { get => $this->attributes[2]; }

    public static function create(?string $leadingWs, BlockCommentNode|LineCommentNode $lineComment): self
    {
        $node = new self(
            name: 'leadingComment',
            origin: NodeOrigin::Sequence,
            attributes: [
                new OptionalRawAttribute(
                    $leadingWs !== null
                        ? new RawRegionAttribute(opener: null, content: $leadingWs, closer: null, name: 'leadingWs', anchorName: 'leadingWs')
                        : null,
                    name: 'leadingWs',
                    anchorName: 'leadingWs',
                ),
                NodeAttribute::fromNode($lineComment),
            ],
            parent: null,
        );
        $lineComment->setParent($node);

        return $node;
    }

    public function getRawLeadingWs(): ?string
    {
        return $this->leadingWs->raw?->content;
    }

    public function setRawLeadingWs(?string $value): self
    {
        if ($value === null) {
            $this->leadingWs->raw = null;
        } elseif ($this->leadingWs->raw instanceof RawRegionAttribute) {
            $this->leadingWs->raw->content = $value;
        } else {
            $this->leadingWs->raw = new RawRegionAttribute(opener: null, content: $value, closer: null, name: 'leadingWs', anchorName: 'leadingWs');
        }
        return $this;
    }

    public function getNodeLineComment(): BlockCommentNode|LineCommentNode
    {
        /** @var BlockCommentNode|LineCommentNode $node */
        $node = $this->lineComment->node;
        return $node;
    }

    public function setNodeLineComment(BlockCommentNode|LineCommentNode $value): self
    {
        $this->attributes[1] = NodeAttribute::fromNode($value->setParent($this));
        return $this;
    }
}
