<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\TreeSchema\Model\Json\C;

use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\RawContentAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\StructureAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Node;

class BlockCommentNode extends Node
{
    public StructureAttribute $blockCommentStart { get => $this->attributes[0]; }
    public RawContentAttribute $space { get => $this->attributes[1]; }
    public StructureAttribute $blockCommentEnd { get => $this->attributes[2]; }

    public static function create(string $space = ' f '): self
    {
        return new self(
            name: 'blockComment',
            attributes: [
            new StructureAttribute(true, 'blockCommentStart', '/*'),
            new RawContentAttribute($space),
            new StructureAttribute(true, 'blockCommentEnd', '*/'),
            ],
            parent: null,
        );
    }

    public function getRawSpace(): string
    {
        return $this->space->content;
    }

    public function setRawSpace(string $value): self
    {
        $this->space->content = $value;
        return $this;
    }
}
