<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Parsing\Model;

use LogicException;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeAttributeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\Placement;

/**
 * A node whose body is exactly one attribute (Table 2: Token/*, Region/Raw,
 * Region/Structure, Sequence/Raw, Sequence/Structure). Enforces the single-attribute
 * invariant on add; the value is read via {@see getAttribute()}.
 */
class LeafNode extends AbstractNode
{
    public function addAttribute(NodeAttributeInterface $attribute, Placement $placement = Placement::After, int $offset = -1): self
    {
        if ($this->attributes !== []) {
            throw new LogicException(sprintf(
                "LeafNode '%s' holds exactly one attribute; cannot add '%s'.",
                $this->name,
                $attribute->getName(),
            ));
        }

        return parent::addAttribute($attribute, $placement, $offset);
    }

    public function getAttribute(): NodeAttributeInterface
    {
        return $this->attributes[0]
            ?? throw new LogicException("LeafNode '{$this->name}' has no attribute.");
    }

    /**
     * Replaces the single attribute (or sets it when empty). The structural swap
     * does not need any formatting context — the caller supplies the replacement.
     */
    public function replaceAttribute(NodeAttributeInterface $replacement): self
    {
        if ($this->attributes !== []) {
            $this->removeAttributeByOffset(0);
        }

        return $this->addAttribute($replacement);
    }
}
