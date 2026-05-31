<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute;

use PhpArchitecture\Parser\Foundation\Matching\Model\NestedSequence;
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
    public const DEFAULT_NAME = 'grouped';
    public const ANCHOR_NAME_META_KEY = 'groupedAnchorName';

    /** @var NodeAttributeInterface[] */
    public array $attributes;

    private ?SequenceValidityCursor $validityCursor = null;

    /**
     * @param NodeAttributeInterface[] $attributes
     * @param array<string,mixed> $meta
     * @param string[] $tags
     */
    public function __construct(
        public readonly string $name,
        public ?NodeInterface $parent,
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
        $this->validityCursor?->advance($attr->getName());

        $this->attributes[] = $this->parent ? $attr->withParent($this->parent) : $attr;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function withParent(NodeInterface $parent): static
    {
        $this->parent = $parent;
        foreach ($this->attributes as $attr) {
            $attr->withParent($parent);
        }
        return $this;
    }

    public function withValidSequence(NestedSequence|SequenceValidityCursor $sequence): static
    {
        $this->validityCursor = $sequence instanceof SequenceValidityCursor
            ? $sequence
            : new SequenceValidityCursor($sequence);

        foreach ($this->attributes as $attr) {
            $this->validityCursor->advance($attr->getName());
        }

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
