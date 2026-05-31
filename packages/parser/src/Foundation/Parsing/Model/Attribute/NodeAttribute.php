<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute;

use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeAttributeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Shared\Meta\MetaInterface;
use PhpArchitecture\Parser\Foundation\Shared\Meta\MetaTrait;
use PhpArchitecture\Parser\Foundation\Shared\Tags\TagsTrait;

/**
 * @template T of NodeInterface
 */
class NodeAttribute implements NodeAttributeInterface, MetaInterface
{
    use MetaTrait;
    use TagsTrait;

    /**
     * @param T $node
     * @param array<string,mixed> $meta
     * @param string[] $tags
     */
    public function __construct(
        public string $name,
        public NodeInterface $node,
        array $meta = [],
        array $tags = [],
    ) {
        $this->meta = $meta;
        $this->tags = $tags;
    }

    public static function fromNode(NodeInterface $node, array $meta = [], array $tags = []): self
    {
        return new self(
            name: $node->getName(),
            node: $node,
            meta: array_merge($node->getMetaAll(), $meta),
            tags: array_merge($node->getAllTags(), $tags),
        );
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function withParent(NodeInterface $parent): static
    {
        $this->node->setParent($parent);
        return $this;
    }

    public function __toString(): string
    {
        return $this->node->__toString();
    }
}
