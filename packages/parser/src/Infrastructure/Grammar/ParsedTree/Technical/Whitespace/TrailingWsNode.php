<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace;

use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\RawContentAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Node;

class TrailingWsNode extends Node
{
    public RawContentAttribute $raw { get => $this->attributes[0]; }

    public static function create(string $raw = "\n", ?NodeInterface $parent = null): self
    {
        $node = new self(
            name: 'trailingWs',
            attributes: [new RawContentAttribute($raw)],
            parent: $parent,
        );

        return $node;
    }
}
