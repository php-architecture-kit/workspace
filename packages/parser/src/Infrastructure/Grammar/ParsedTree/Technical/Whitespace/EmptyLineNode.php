<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace;

use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawRegionAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\LeafNode;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeOrigin;

class EmptyLineNode extends LeafNode
{
    public RawRegionAttribute $emptyLine { get => $this->attributes[0]; }

    public static function create(string $emptyLine): self
    {
        return new self(
            name: 'emptyLine',
            origin: NodeOrigin::Region,
            attributes: [
                new RawRegionAttribute(
                    opener: null,
                    closer: null,
                    content: $emptyLine,
                    name: 'emptyLine',
                    anchorName: null,
                ),
            ],
            parent: null,
        );
    }

    public function getRawEmptyLine(): string
    {
        return $this->emptyLine->content;
    }

    public function setRawEmptyLine(string $emptyLine): self
    {
        $this->emptyLine->content = $emptyLine;
        return $this;
    }
}
