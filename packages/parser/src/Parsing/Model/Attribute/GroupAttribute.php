<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Parsing\Model\Attribute;

use PhpArchitecture\Parser\Processing\Model\Parsing\NodeAttributeInterface;
use PhpArchitecture\Parser\Processing\Model\Parsing\NodeInterface;
use PhpArchitecture\Parser\Shared\Meta\MetaInterface;
use PhpArchitecture\Parser\Shared\Meta\MetaTrait;
use PhpArchitecture\Parser\Shared\Tags\TagsTrait;

class GroupAttribute implements NodeAttributeInterface, MetaInterface
{
    use MetaTrait;
    use TagsTrait;

    /**
     * @param NodeInterface[] $nodes
     * @param array<string,mixed> $meta
     * @param string[] $tags
     */
    public function __construct(
        public string $name,
        public array $nodes,
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
        return implode('', array_map(
            static fn(NodeInterface $node) => $node->__toString(),
            $this->nodes,
        ));
    }
}
