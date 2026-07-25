<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Parsing\Model;

use LogicException;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeAttributeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\Placement;

/**
 * A node whose body is an unordered collection of 0..N children of allowed types
 * (Table 2: Region/Node). Children are addressed by identity (mutation-safe) or
 * filtered by name; `addAttribute` (append, inherited) is the write path.
 */
class GroupNode extends AbstractNode
{
    /**
     * All child attributes with the given name, in document order.
     *
     * @return NodeAttributeInterface[]
     */
    public function getByName(string $name): array
    {
        return array_values(array_filter(
            $this->attributes,
            static fn(NodeAttributeInterface $attr): bool => $attr->getName() === $name,
        ));
    }

    public function removeAttribute(NodeAttributeInterface $attribute): self
    {
        $this->removeAttributeByOffset($this->requireOffsetOf($attribute));

        return $this;
    }

    public function replaceAttribute(NodeAttributeInterface $existing, NodeAttributeInterface $replacement): self
    {
        $offset = $this->requireOffsetOf($existing);
        $this->removeAttributeByOffset($offset);
        $this->addAttribute($replacement, Placement::Before, $offset);

        return $this;
    }

    private function requireOffsetOf(NodeAttributeInterface $attribute): int
    {
        foreach ($this->attributes as $offset => $existing) {
            if ($existing === $attribute) {
                return $offset;
            }
        }

        throw new LogicException("Attribute '{$attribute->getName()}' is not a child of group node '{$this->name}'.");
    }
}
