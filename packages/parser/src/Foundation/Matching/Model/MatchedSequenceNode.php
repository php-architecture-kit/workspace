<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Matching\Model;

use PhpArchitecture\Parser\Foundation\Tokenization\Model\Token;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\TokenRegion;
use PhpArchitecture\Parser\Foundation\Shared\Meta\MetaInterface;
use PhpArchitecture\Parser\Foundation\Shared\Meta\MetaTrait;
use PhpArchitecture\Parser\Foundation\Shared\Tags\TagsTrait;
use Stringable;

class MatchedSequenceNode implements MetaInterface, Stringable
{
    use MetaTrait;
    use TagsTrait;

    /**
     * @param string|null $anchorName
     * @param array<string> $alternatives
     * @param array<Token|TokenRegion|MatchedSequence> $items
     * @param array<string,mixed> $meta
     * @param string[] $tags
     */
    public function __construct(
        public private(set) ?string $anchorName,
        public private(set) array $alternatives,
        public private(set) array $items,
        public private(set) bool $isNegation,
        public private(set) int $min,
        public private(set) int $max,
        array $meta,
        array $tags,
    ) {
        $this->meta = $meta;
        $this->tags = $tags;
    }

    public function getName(): string
    {
        return $this->anchorName ?? ($this->isNegation
            ? '!' . implode('|', $this->alternatives)
            : implode('|', $this->alternatives));
    }

    public function getComprehensiveMeta(): array
    {
        return array_merge(
            $this->meta,
            [
                'anchorName' => $this->anchorName,
                'alternatives' => $this->alternatives,
                'isNegation' => $this->isNegation,
                'min' => $this->min,
                'max' => $this->max,
            ],
        );
    }

    public function __toString(): string
    {
        return implode('', array_map(
            static fn(Token|TokenRegion|MatchedSequence $item): string => $item->__toString(),
            $this->items,
        ));
    }
}
