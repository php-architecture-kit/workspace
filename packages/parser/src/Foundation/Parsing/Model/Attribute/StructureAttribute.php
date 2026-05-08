<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute;

use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeAttributeInterface;
use PhpArchitecture\Parser\Foundation\Shared\Meta\MetaInterface;
use PhpArchitecture\Parser\Foundation\Shared\Meta\MetaTrait;
use PhpArchitecture\Parser\Foundation\Shared\Tags\TagsTrait;

class StructureAttribute implements NodeAttributeInterface, MetaInterface
{
    use MetaTrait;
    use TagsTrait;

    public const DEFAULT_NAME = '__raw__';
    public string $content;

    /**
     * @param array<string,mixed> $meta
     * @param string[] $tags
     */
    public function __construct(
        public bool $present,
        public string $name = self::DEFAULT_NAME,
        ?string $content = null,
        array $meta = [],
        array $tags = [],
    ) {
        $this->meta = $meta;
        $this->tags = $tags;
        $this->content = $content ?? $this->meta[self::DEFAULT_VALUE_KEY] ?? '';
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function __toString(): string
    {
        return $this->present ? $this->content : '';
    }
}
