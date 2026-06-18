<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute;

use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeAttributeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Sequence\HoldsAttributes;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Sequence\SequenceCarrier;
use PhpArchitecture\Parser\Foundation\Shared\Meta\MetaInterface;
use PhpArchitecture\Parser\Foundation\Shared\Meta\MetaTrait;
use PhpArchitecture\Parser\Foundation\Shared\Tags\TagsTrait;

/**
 * A nested `/g` sub-run promoted to one anchor-named, addressable attribute of its
 * parent sequence. The grammar-validated sequence machinery (validity cursor,
 * content/structural classification, units) lives in {@see SequenceCarrier}, shared
 * with the Sequence-origin shape node; this class adds the attribute role: a name,
 * an owning node, and parent propagation.
 */
class SequenceAttribute implements NodeAttributeInterface, MetaInterface
{
    use MetaTrait;
    use TagsTrait;
    use HoldsAttributes;
    use SequenceCarrier;

    public const TAG = 'SequenceAttribute';
    public const CONTENT_TAG = 'SequenceAttribute.content';
    public const DEFAULT_NAME = 'sequence';
    public const ANCHOR_NAME_META_KEY = 'sequenceAnchorName';

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

    protected function attributesOwner(): ?NodeInterface
    {
        return $this->parent;
    }
}
