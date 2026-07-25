<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Parsing\Model\Sequence;

use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeAttributeInterface;

/**
 * Ordered attribute storage + the primitive operations shared by everything that
 * holds an attribute list: shape nodes (via AbstractNode) and SequenceAttribute.
 *
 * Pure mechanics only — no validation, no classification. Policy lives in the
 * shape-specific traits ({@see SequenceCarrier}) and node subclasses.
 */
trait HoldsAttributes
{
    /** @var NodeAttributeInterface[] */
    public array $attributes = [];

    /** @return NodeAttributeInterface[] */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function __toString(): string
    {
        return implode('', array_map(
            static fn(NodeAttributeInterface $attr) => $attr->__toString(),
            $this->attributes,
        ));
    }

    protected function insertAttributeAt(NodeAttributeInterface $attr, int $offset): void
    {
        array_splice($this->attributes, $offset, 0, [$attr]);
    }

    protected function removeAttributeAt(int $offset): void
    {
        array_splice($this->attributes, $offset, 1);
    }

    /**
     * Offset of $attr by identity, or null when absent. Computed lazily (no cached
     * indices) so it stays correct across mutations.
     */
    public function indexOfAttribute(NodeAttributeInterface $attr): ?int
    {
        foreach ($this->attributes as $offset => $existing) {
            if ($existing === $attr) {
                return $offset;
            }
        }

        return null;
    }
}
