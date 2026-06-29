<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Comment;

use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\OptionalRawAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawContentAttribute;
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

    public static function create(string $content): self
    {
        return new self(
            name: 'singleLine',
            origin: NodeOrigin::Sequence,
            attributes: [
                new StructureAttribute(true, 'blockCommentStart', '/*'),
                new StructureAttribute(true, 'openingAsterisk', '*'),
                new RawContentAttribute($content),
                new StructureAttribute(true, 'closingAsterisk', '*'),
                new StructureAttribute(true, 'blockCommentEnd', '*/'),
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
