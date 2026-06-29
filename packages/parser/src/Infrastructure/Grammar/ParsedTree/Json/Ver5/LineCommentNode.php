<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Ver5;

use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\OptionalRawAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawContentAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Structure\StructureAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeOrigin;
use PhpArchitecture\Parser\Foundation\Parsing\Model\SequenceNode;

class LineCommentNode extends SequenceNode
{
    public StructureAttribute $lineCommentStart { get => $this->attributes[0]; }

    public OptionalRawAttribute $leadingWs { get => $this->attributes[1]; }

    public RawContentAttribute $content { get => $this->attributes[2]; }

    public OptionalRawAttribute $trailingWs { get => $this->attributes[3]; }

    public static function create(string $content): self
    {
        return new self(
            name: 'lineComment',
            origin: NodeOrigin::Sequence,
            attributes: [
                new StructureAttribute(true, 'lineCommentStart', '//'),
                new RawContentAttribute($content),
            ],
            parent: null,
        );
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
}
