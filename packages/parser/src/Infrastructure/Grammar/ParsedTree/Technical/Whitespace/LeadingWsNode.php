<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace;

use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawRegionAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\LeafNode;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeOrigin;

class LeadingWsNode extends LeafNode
{
    public RawRegionAttribute $leadingWs { get => $this->attributes[0]; }

    public static function create(string $leadingWs): self
    {
        return new self(
            name: 'leadingWs',
            origin: NodeOrigin::Region,
            attributes: [
                new RawRegionAttribute(
                    opener: null,
                    closer: null,
                    content: $leadingWs,
                    name: 'leadingWs',
                    anchorName: null,
                ),
            ],
            parent: null,
        );
    }

    public function getRawLeadingWs(): string
    {
        return $this->leadingWs->content;
    }

    public function setRawLeadingWs(string $leadingWs): self
    {
        $this->leadingWs->content = $leadingWs;
        return $this;
    }
}
