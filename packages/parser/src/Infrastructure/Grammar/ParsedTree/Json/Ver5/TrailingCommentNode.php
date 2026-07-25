<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Ver5;

use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\NodeAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\OptionalRawAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawGroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawRegionAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeOrigin;
use PhpArchitecture\Parser\Foundation\Parsing\Model\SequenceNode;

class TrailingCommentNode extends SequenceNode
{
    public OptionalRawAttribute $leadingWs { get => $this->attributes[0]; }

    /** @var NodeAttribute<BlockCommentNode|LineCommentNode> */
    public NodeAttribute $comment { get => $this->attributes[1]; }

    public RawGroupAttribute $trailingWs { get => $this->attributes[2]; }

    public static function create(?string $leadingWs, BlockCommentNode|LineCommentNode $comment): self
    {
        $node = new self(
            name: 'trailingComment',
            origin: NodeOrigin::Sequence,
            attributes: [
                new OptionalRawAttribute(
                    $leadingWs !== null
                        ? new RawRegionAttribute(opener: null, content: $leadingWs, closer: null, name: 'inlineWs', anchorName: 'leadingWs')
                        : null,
                    name: 'leadingWs',
                    anchorName: 'leadingWs',
                ),
                NodeAttribute::fromNode($comment),
            ],
            parent: null,
        );
        $comment->setParent($node);

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
            $this->leadingWs->raw = new RawRegionAttribute(opener: null, content: $value, closer: null, name: 'inlineWs', anchorName: 'leadingWs');
        }
        return $this;
    }

    public function getNodeComment(): BlockCommentNode|LineCommentNode
    {
        /** @var BlockCommentNode|LineCommentNode $node */
        $node = $this->comment->node;
        return $node;
    }

    public function setNodeComment(BlockCommentNode|LineCommentNode $value): self
    {
        $this->attributes[1] = NodeAttribute::fromNode($value->setParent($this));
        return $this;
    }
}
