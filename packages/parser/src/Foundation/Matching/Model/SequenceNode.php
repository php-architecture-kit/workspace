<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Matching\Model;

use PhpArchitecture\Parser\Foundation\Shared\Meta\MetaInterface;
use PhpArchitecture\Parser\Foundation\Shared\Meta\MetaTrait;
use PhpArchitecture\Parser\Foundation\Shared\Tags\TagsTrait;

final class SequenceNode implements MetaInterface
{
    use MetaTrait;
    use TagsTrait;

    /**
     * @param string[] $alternatives
     * @param string[] $tags
     */
    public function __construct(
        public array $alternatives,
        public int $min,
        public int $max,
        public bool $isLookahead = false,
        public bool $isLookbehind = false,
        public ?string $anchorName = null,
        array $meta = [],
        array $tags = [],
        public bool $isNegation = false,
    ) {
        $this->meta = $meta;
        $this->tags = $tags;
    }

    /**
     * @return string[]
     */
    public function getAllNodeNames(): array
    {
        return $this->alternatives;
    }
}
