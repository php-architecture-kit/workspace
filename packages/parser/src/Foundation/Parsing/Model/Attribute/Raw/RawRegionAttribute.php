<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw;

use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Shared\Meta\MetaTrait;
use PhpArchitecture\Parser\Foundation\Shared\Tags\TagsTrait;

class RawRegionAttribute implements RawAttributeInterface
{
    use MetaTrait;
    use TagsTrait;

    /**
     * @param array<string,mixed> $meta
     * @param string[] $tags
     */
    public function __construct(
        public ?string $opener,
        public ?string $content,
        public ?string $closer,
        public string $name = self::DEFAULT_NAME,
        public ?string $anchorName = null,
        array $meta = [],
        array $tags = [],
    ) {
        $this->meta = $meta;
        $this->tags = $tags;
    }

    public function getName(): string
    {
        return $this->anchorName ?? $this->name;
    }

    public function withParent(NodeInterface $parent): static
    {
        return $this;
    }

    public function __toString(): string
    {
        return $this->opener . $this->content . $this->closer;
    }
}
