<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Processing\Model\Matching;

use PhpArchitecture\Parser\Processing\Model\Tokenization\Token;
use PhpArchitecture\Parser\Processing\Model\Tokenization\TokenRegion;
use PhpArchitecture\Parser\Shared\Meta\MetaInterface;
use PhpArchitecture\Parser\Shared\Meta\MetaTrait;
use PhpArchitecture\Parser\Shared\Tags\TagsTrait;
use Stringable;

class MatchedSequenceNode implements MetaInterface, Stringable
{
    use MetaTrait;
    use TagsTrait;

    /**
     * @param array<Token|TokenRegion|MatchedSequence> $items
     * @param array<string,mixed> $meta
     * @param string[] $tags
     */
    public function __construct(
        public private(set) string $name,
        public private(set) array $items,
        array $meta,
        array $tags,
        public private(set) bool $isSpread = false,
    ) {
        $this->meta = $meta;
        $this->tags = $tags;
    }

    public function __toString(): string
    {
        return implode('', array_map(
            static fn(Token|TokenRegion|MatchedSequence $item): string => $item->__toString(),
            $this->items,
        ));
    }
}
