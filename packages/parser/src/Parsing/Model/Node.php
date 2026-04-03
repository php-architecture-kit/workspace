<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Parsing\Model;

use PhpArchitecture\Parser\Processing\Model\Parsing\NodeInterface;
use PhpArchitecture\Parser\Shared\Meta\MetaInterface;
use PhpArchitecture\Parser\Shared\Meta\MetaTrait;
use PhpArchitecture\Parser\Shared\Tags\TagsTrait;

class Node implements NodeInterface, MetaInterface
{
    use MetaTrait;
    use TagsTrait;

    /**
     * @param array<int,NodeInterface> $attributes
     * @param array<string,mixed> $meta
     * @param string[] $tags
     */
    public function __construct(
        public string $name,
        public array $attributes,
        array $meta = [],
        array $tags = [],
    ) {
        $this->meta = $meta;
        $this->tags = $tags;
    }

    public function __toString(): string
    {
        return implode('', array_map(
            static fn(NodeInterface $attr) => $attr->__toString(),
            $this->attributes
        ));
    }
}
