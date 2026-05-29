<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Parsing\Model;

use PhpArchitecture\Parser\Foundation\Parsing\Contract\AttributePlacement;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeAttributeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Shared\Meta\MetaInterface;
use PhpArchitecture\Parser\Foundation\Shared\Meta\MetaTrait;
use PhpArchitecture\Parser\Foundation\Shared\Tags\TagsTrait;
use WeakReference;
use LogicException;

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

    /** @return NodeAttributeInterface[] */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getParent(): null|NodeInterface
    {
        return $this->parent?->get();
    }

    public function __get(string $name): NodeAttributeInterface
    {
        foreach ($this->attributes as $attribute) {
            if ($attribute->getName() === $name) {

                return $attribute;
            }
        }

        throw new LogicException("Attribute '{$name}' not found on node '{$this->name}'");
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

    public function setParent(NodeInterface $parent): self
    {
        $this->parent = WeakReference::create($parent);

        return $this;
    }

    public function __toString(): string
    {
        return implode('', array_map(
            static fn(NodeAttributeInterface $attr) => $attr->__toString(),
            $this->attributes,
        ));
    }
}
