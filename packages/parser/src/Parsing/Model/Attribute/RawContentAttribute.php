<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Parsing\Model\Attribute;

use PhpArchitecture\Parser\Processing\Model\Parsing\NodeAttributeInterface;
use PhpArchitecture\Parser\Shared\Meta\MetaInterface;
use PhpArchitecture\Parser\Shared\Meta\MetaTrait;
use PhpArchitecture\Parser\Shared\Tags\TagsTrait;

class RawContentAttribute implements NodeAttributeInterface, MetaInterface
{
    use MetaTrait;
    use TagsTrait;

    public const DEFAULT_NAME = '__raw__';

    /**
     * @param array<string,mixed> $meta
     * @param string[] $tags
     */
    public function __construct(
        public string $content,
        public string $name = self::DEFAULT_NAME,
        array $meta = [],
        array $tags = [],
    ) {
        $this->meta = $meta;
        $this->tags = $tags;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function __toString(): string
    {
        return $this->content;
    }
}
