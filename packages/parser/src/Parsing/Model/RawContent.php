<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Parsing\Model;

use PhpArchitecture\Parser\Processing\Model\Parsing\NodeInterface;
use PhpArchitecture\Parser\Shared\Meta\MetaInterface;
use PhpArchitecture\Parser\Shared\Meta\MetaTrait;
use PhpArchitecture\Parser\Shared\Tags\TagsTrait;

class RawContent implements NodeInterface, MetaInterface
{
    use MetaTrait;
    use TagsTrait;

    /**
     * @param array<string,mixed> $meta
     * @param string[] $tags
     */
    public function __construct(
        public string $name,
        public string $content,
        array $meta = [],
        array $tags = [],
    ) {
        $this->meta = $meta;
        $this->tags = $tags;
    }

    public function __toString(): string
    {
        return $this->content;
    }
}
