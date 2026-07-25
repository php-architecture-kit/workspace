<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace;

use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawRegionAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\LeafNode;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeOrigin;

class TrailingWsNode extends LeafNode
{
    public RawRegionAttribute $trailingWs { get => $this->attributes[0]; }

    public static function create(string $trailingWs): self
    {
        return new self(
            name: 'trailingWs',
            origin: NodeOrigin::Region,
            attributes: [
                new RawRegionAttribute(
                    opener: null,
                    closer: null,
                    content: $trailingWs,
                    name: 'trailingWs',
                    anchorName: null,
                ),
            ],
            parent: null,
        );
    }

    public function getRawTrailingWs(): string
    {
        return $this->trailingWs->content;
    }

    public function setRawTrailingWs(string $trailingWs): self
    {
        $this->trailingWs->content = $trailingWs;
        return $this;
    }
}
