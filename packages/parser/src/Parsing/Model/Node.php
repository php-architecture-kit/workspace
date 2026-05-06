<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Parsing\Model;

use PhpArchitecture\Parser\Processing\Model\Parsing\AttributePlacement;
use PhpArchitecture\Parser\Processing\Model\Parsing\NodeAttributeInterface;
use PhpArchitecture\Parser\Processing\Model\Parsing\NodeInterface;
use PhpArchitecture\Parser\Shared\Meta\MetaInterface;
use PhpArchitecture\Parser\Shared\Meta\MetaTrait;
use PhpArchitecture\Parser\Shared\Tags\TagsTrait;
use WeakReference;

class Node implements NodeInterface, MetaInterface
{
    use MetaTrait;
    use TagsTrait;

    /** @var WeakReference<NodeInterface>|null */
    public private(set) ?WeakReference $parent = null;

    /**
     * @param NodeAttributeInterface[] $attributes
     * @param array<string,mixed> $meta
     * @param string[] $tags
     */
    public function __construct(
        public readonly string $name,
        public private(set) array $attributes,
        ?NodeInterface $parent,
        array $meta = [],
        array $tags = [],
    ) {
        if ($parent !== null) {
            $this->parent = WeakReference::create($parent);
        }
        $this->meta = $meta;
        $this->tags = $tags;
    }

    public function addAttribute(NodeAttributeInterface $attribute, AttributePlacement $placement = AttributePlacement::After, int $offset = -1): self
    {
        if ($offset < 0) {
            $offset = count($this->attributes) + $offset + 1;
        }

        $offset = match ($placement) {
            AttributePlacement::Before => $offset,
            AttributePlacement::After => $offset + 1,
        };

        array_splice($this->attributes, $offset, 0, [$attribute]);

        return $this;
    }

    public function removeAttributeByOffset(int $offset): self
    {
        array_splice($this->attributes, $offset, 1);
        return $this;
    }

    /**
     * @param callable(NodeAttributeInterface):bool $filter true - stay, false - remove
     */
    public function removeAttributeByFilter(callable $filter): self
    {
        $this->attributes = array_filter($this->attributes, $filter);
        return $this;
    }

    public function withParent(NodeInterface $parent): self
    {
        return new self(
            $this->name,
            $this->attributes,
            $parent,
            $this->meta,
            $this->tags,
        );
    }

    public function __toString(): string
    {
        return implode('', array_map(
            static fn(NodeAttributeInterface $attr) => $attr->__toString(),
            $this->attributes,
        ));
    }
}
