<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute;

use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeAttributeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Shared\Meta\MetaInterface;
use PhpArchitecture\Parser\Foundation\Shared\Meta\MetaTrait;
use PhpArchitecture\Parser\Foundation\Shared\Tags\TagsTrait;

class GroupedAttribute implements NodeAttributeInterface, MetaInterface
{
    use MetaTrait;
    use TagsTrait;

    public const TAG = 'GroupedAttribute';

    /** @var NodeAttributeInterface[] */
    public array $attributes;

    /**
     * @param NodeAttributeInterface[] $attributes
     * @param array<string,mixed> $meta
     * @param string[] $tags
     */
    public function __construct(
        public readonly string $name,
        public readonly NodeInterface $parent,
        array $attributes = [],
        array $meta = [],
        array $tags = [],
    ) {
        $this->attributes = $attributes;
        $this->meta = $meta;
        $this->tags = $tags;
    }

    public function addAttribute(NodeAttributeInterface $attr): void
    {
        $this->attributes[] = $attr->withParent($this->parent);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function withParent(NodeInterface $parent): static
    {
        return new static($this->name, $parent, $this->attributes, $this->meta, $this->tags);
    }

    public function __toString(): string
    {
        return implode('', array_map(
            static fn(NodeAttributeInterface $attr) => $attr->__toString(),
            $this->attributes,
        ));
    }
}
