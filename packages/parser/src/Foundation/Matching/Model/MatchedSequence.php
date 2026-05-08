<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Matching\Model;

use PhpArchitecture\Parser\Foundation\Shared\Meta\MetaInterface;
use PhpArchitecture\Parser\Foundation\Shared\Meta\MetaTrait;
use PhpArchitecture\Parser\Foundation\Shared\Tags\TagsTrait;
use Stringable;

class MatchedSequence implements MetaInterface, Stringable
{
    use MetaTrait;
    use TagsTrait;

    /**
     * @param MatchedSequenceNode[] $items
     * @param array<string,mixed> $meta
     * @param string[] $tags
     */
    public function __construct(
        public private(set) string $name,
        public private(set) array $items,
        array $meta,
        array $tags,
    ) {
        $this->meta = $meta;
        $this->tags = $tags;
    }

    public function __toString(): string
    {
        return implode('', array_map(
            static fn(MatchedSequenceNode $node): string => $node->__toString(),
            $this->items,
        ));
    }
}
