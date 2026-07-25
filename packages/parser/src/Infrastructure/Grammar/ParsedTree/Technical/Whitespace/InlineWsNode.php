<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace;

use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawRegionAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\LeafNode;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeOrigin;

class InlineWsNode extends LeafNode
{
    public RawRegionAttribute $inlineWs { get => $this->attributes[0]; }

    public static function create(string $inlineWs): self
    {
        return new self(
            name: 'inlineWs',
            origin: NodeOrigin::Region,
            attributes: [
                new RawRegionAttribute(
                    opener: null,
                    closer: null,
                    content: $inlineWs,
                    name: 'inlineWs',
                    anchorName: null,
                ),
            ],
            parent: null,
        );
    }

    public function getRawInlineWs(): string
    {
        return $this->inlineWs->content;
    }

    public function setRawInlineWs(string $inlineWs): self
    {
        $this->inlineWs->content = $inlineWs;
        return $this;
    }
}
