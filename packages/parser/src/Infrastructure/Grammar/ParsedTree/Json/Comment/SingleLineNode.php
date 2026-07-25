<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Comment;

use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\OptionalRawAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawContentAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawRegionAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Structure\StructureAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeOrigin;
use PhpArchitecture\Parser\Foundation\Parsing\Model\SequenceNode;

class SingleLineNode extends SequenceNode
{
    public StructureAttribute $blockCommentStart { get => $this->attributes[0]; }
    public StructureAttribute $openingAsterisk { get => $this->attributes[1]; }
    public OptionalRawAttribute $leadingWs { get => $this->attributes[2]; }
    public RawContentAttribute $content { get => $this->attributes[3]; }
    public OptionalRawAttribute $trailingWs { get => $this->attributes[4]; }
    public StructureAttribute $closingAsterisk { get => $this->attributes[5]; }
    public StructureAttribute $blockCommentEnd { get => $this->attributes[6]; }

    public static function create(?string $leadingWs, string $content, ?string $trailingWs = null): self
    {
        return new self(
            name: 'singleLine',
            origin: NodeOrigin::Sequence,
            attributes: [
                new StructureAttribute(true, 'blockCommentStart', '/*'),
                new StructureAttribute(true, 'openingAsterisk', '*'),
                new OptionalRawAttribute(
                    $leadingWs !== null
                        ? new RawRegionAttribute(opener: null, content: $leadingWs, closer: null, name: 'inlineWs', anchorName: 'leadingWs')
                        : null,
                    name: 'leadingWs',
                    anchorName: 'leadingWs',
                ),
                new RawContentAttribute($content),
                new OptionalRawAttribute(
                    $trailingWs !== null
                        ? new RawRegionAttribute(opener: null, content: $trailingWs, closer: null, name: 'inlineWs', anchorName: 'trailingWs')
                        : null,
                    name: 'trailingWs',
                    anchorName: 'trailingWs',
                ),
                new StructureAttribute(true, 'closingAsterisk', '*'),
                new StructureAttribute(true, 'blockCommentEnd', '*/'),
            ],
            parent: null,
        );
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

    public function getRawContent(): string
    {
        return $this->content->content;
    }

    public function setRawContent(string $value): self
    {
        $this->content->content = $value;
        return $this;
    }

    public function getRawTrailingWs(): ?string
    {
        return $this->trailingWs->raw?->content;
    }

    public function setRawTrailingWs(?string $value): self
    {
        if ($value === null) {
            $this->trailingWs->raw = null;
        } elseif ($this->trailingWs->raw instanceof RawRegionAttribute) {
            $this->trailingWs->raw->content = $value;
        } else {
            $this->trailingWs->raw = new RawRegionAttribute(opener: null, content: $value, closer: null, name: 'inlineWs', anchorName: 'trailingWs');
        }
        return $this;
    }
}
