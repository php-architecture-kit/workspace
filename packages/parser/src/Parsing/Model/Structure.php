<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Parsing\Model;

use PhpArchitecture\Parser\Processing\Model\Parsing\NodeInterface;
use PhpArchitecture\Parser\Shared\Meta\MetaInterface;
use PhpArchitecture\Parser\Shared\Meta\MetaTrait;
use PhpArchitecture\Parser\Shared\Tags\TagsTrait;
use Stringable;

class Structure implements NodeInterface, MetaInterface, Stringable
{
    use MetaTrait;
    use TagsTrait;

    public string $content;

    /**
     * @param array<string,mixed> $meta
     * @param string[] $tags
     */
    public function __construct(
        public string $name,
        public bool $present,
        ?string $content = null,
        array $meta = [],
        array $tags = [],
    ) {
        $this->meta = $meta;
        $this->tags = $tags;
        $this->content = $content ?? $this->meta[self::DEFAULT_VALUE_KEY] ?? '';
    }

    public function __toString(): string
    {
        return $this->present ? $this->content : '';
    }
}
