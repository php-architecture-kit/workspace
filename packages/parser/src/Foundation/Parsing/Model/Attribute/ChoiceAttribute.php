<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute;

use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeAttributeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Shared\Meta\MetaInterface;
use PhpArchitecture\Parser\Foundation\Shared\Meta\MetaTrait;
use PhpArchitecture\Parser\Foundation\Shared\Tags\TagsTrait;

class ChoiceAttribute implements NodeAttributeInterface, MetaInterface
{
    use MetaTrait;
    use TagsTrait;

    public const TAG = 'ChoiceAttribute';

    /**
     * @param string[] $choices
     * @param array<string,mixed> $meta
     * @param string[] $tags
     */
    public function __construct(
        public readonly string $name,
        public readonly array $choices,
        public private(set) ?NodeAttributeInterface $selected,
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

    public function setSelected(NodeAttributeInterface $selected): void
    {
        if (!in_array($selected->getName(), $this->choices, true)) {
            throw new \InvalidArgumentException("Selected attribute must be one of the defined choices.");
        }

        $this->selected = $selected;
    }

    public function removeSelected(): void
    {
        $this->selected = null;
    }

    public function withParent(NodeInterface $parent): static
    {
        $this->selected?->withParent($parent);
        return $this;
    }

    public function __toString(): string
    {
        return (string) $this->selected;
    }
}
