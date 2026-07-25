<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\C;

use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\OptionalRawAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawContentAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawRegionAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Structure\StructureAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeOrigin;
use PhpArchitecture\Parser\Foundation\Parsing\Model\SequenceNode;

class LineCommentNode extends SequenceNode
{
    public StructureAttribute $lineCommentStart { get => $this->attributes[0]; }

    public OptionalRawAttribute $leadingWs { get => $this->attributes[1]; }

    public RawContentAttribute $content { get => $this->attributes[2]; }

    public OptionalRawAttribute $trailingWs { get => $this->attributes[3]; }

    public static function create(?string $leadingWs, string $content, ?string $trailingWs = null): self
    {
        return new self(
            name: 'lineComment',
            origin: NodeOrigin::Sequence,
            attributes: [
                new StructureAttribute(true, 'lineCommentStart', '//'),
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
                        ? new RawRegionAttribute(opener: null, content: $trailingWs, closer: null, name: 'trailingWs', anchorName: 'trailingWs')
                        : null,
                    name: 'trailingWs',
                    anchorName: 'trailingWs',
                ),
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
            $this->trailingWs->raw = new RawRegionAttribute(opener: null, content: $value, closer: null, name: 'trailingWs', anchorName: 'trailingWs');
        }
        return $this;
    }
}
