<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Parsing\Navigation;

use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeAttributeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\GroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\NodeAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\OptionalAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\SequenceAttribute;

/**
 * Document-order navigation over the attributes of a parsed tree.
 *
 * "Document order" = pre-order: every attribute of a node in turn, descending into
 * the node(s)/attributes each attribute contains before moving to the next sibling.
 * This is what indentation needs — e.g. the value of the attribute immediately
 * before the one being formatted (`previousAttribute($x)?->__toString()`), across
 * node boundaries in the whole tree.
 *
 * Positions are resolved by attribute identity and computed on demand (no cached
 * indices), so navigation stays correct as the tree is mutated.
 */
final class TreeNavigator
{
    public function __construct(private readonly NodeInterface $root) {}

    /**
     * The attribute immediately before $attr in document order, or null if $attr is
     * the first attribute (or is not part of this tree).
     */
    public function previousAttribute(NodeAttributeInterface $attr): ?NodeAttributeInterface
    {
        $order = $this->attributesInDocumentOrder();
        foreach ($order as $i => $candidate) {
            if ($candidate === $attr) {
                return $i > 0 ? $order[$i - 1] : null;
            }
        }

        return null;
    }

    /**
     * The attribute immediately after $attr in document order, or null if $attr is
     * the last attribute (or is not part of this tree).
     */
    public function nextAttribute(NodeAttributeInterface $attr): ?NodeAttributeInterface
    {
        $order = $this->attributesInDocumentOrder();
        foreach ($order as $i => $candidate) {
            if ($candidate === $attr) {
                return $order[$i + 1] ?? null;
            }
        }

        return null;
    }

    /**
     * Every attribute in the tree, flattened into pre-order document order.
     *
     * @return NodeAttributeInterface[]
     */
    public function attributesInDocumentOrder(): array
    {
        $order = [];
        $this->collectNode($this->root, $order);

        return $order;
    }

    /** @param NodeAttributeInterface[] $order */
    private function collectNode(NodeInterface $node, array &$order): void
    {
        foreach ($node->getAttributes() as $attr) {
            $order[] = $attr;
            $this->collectAttribute($attr, $order);
        }
    }

    /** @param NodeAttributeInterface[] $order */
    private function collectAttribute(NodeAttributeInterface $attr, array &$order): void
    {
        if ($attr instanceof NodeAttribute) {
            $this->collectNode($attr->node, $order);
            return;
        }

        if ($attr instanceof OptionalAttribute) {
            if ($attr->node !== null) {
                $this->collectNode($attr->node, $order);
            }
            return;
        }

        if ($attr instanceof GroupAttribute) {
            foreach ($attr->nodes as $child) {
                $this->collectNode($child, $order);
            }
            return;
        }

        if ($attr instanceof SequenceAttribute) {
            foreach ($attr->attributes as $inner) {
                $order[] = $inner;
                $this->collectAttribute($inner, $order);
            }
            return;
        }

        // Raw/Structure attributes are leaves — nothing further to descend into.
    }
}
