<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Matching\Model;

use PhpArchitecture\Parser\Foundation\Tokenization\Model\Token;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\TokenRegion;
use PhpArchitecture\Parser\Foundation\Shared\Meta\MetaInterface;
use PhpArchitecture\Parser\Foundation\Shared\Meta\MetaTrait;
use PhpArchitecture\Parser\Foundation\Shared\Tags\TagsTrait;
use Stringable;

class MatchedRegion implements MetaInterface, Stringable
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
    ) {
        $this->meta = $meta;
        $this->tags = $tags;
    }

    public function addItem(Token|TokenRegion|MatchedSequence $item): void
    {
        $this->items[] = $item;
    }

    public function firstItem(): null|Token|TokenRegion|MatchedSequence
    {
        return $this->items[0] ?? null;
    }

    public function lastItem(): null|Token|TokenRegion|MatchedSequence
    {
        $lastIndex = $this->lastIndex();

        return $lastIndex !== null ? $this->items[$lastIndex] : null;
    }

    public function lastIndex(): null|int
    {
        $index = array_key_last($this->items);
        if (is_string($index)) {
            return (int) $index;
        }

        return $index;
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
    }

    public function __toString(): string
    {
        return implode('', array_map(
            static fn(Token|TokenRegion|MatchedSequence $item): string => $item->__toString(),
            $this->items,
        ));
    }
}
