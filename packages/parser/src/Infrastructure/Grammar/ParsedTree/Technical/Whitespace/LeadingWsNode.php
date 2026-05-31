<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace;

use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\RawContentAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Node;

class LeadingWsNode extends Node
{
    public RawContentAttribute $raw { get => $this->attributes[0]; }

    public static function create(string $raw = "\t", ?NodeInterface $parent = null): self
    {
        $node = new self(
            name: 'leadingWs',
            attributes: [new RawContentAttribute($raw)],
            parent: $parent,
        );

        return $node;
    }
}
