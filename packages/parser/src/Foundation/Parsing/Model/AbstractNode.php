<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Parsing\Model;

use PhpArchitecture\Parser\Foundation\ParsedTree\Context\ContextStack;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeAttributeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\Placement;
use PhpArchitecture\Parser\Foundation\Shared\Meta\MetaTrait;
use PhpArchitecture\Parser\Foundation\Shared\Tags\TagsTrait;
use WeakReference;
use LogicException;
use PhpArchitecture\Parser\Foundation\ParsedTree\Context\NodeContext;
use PhpArchitecture\Parser\Foundation\ParsedTree\Format\Formatter;

/**
 * Identity / infrastructure base shared by every parsed-tree node, independent of
 * shape. Structural behavior (how `attributes[]` is shaped, validated, accessed)
 * lives in the concrete shape subclasses: {@see LeafNode}, {@see GroupNode},
 * {@see SequenceNode}.
 */
abstract class AbstractNode implements NodeInterface
{
    use MetaTrait;
    use TagsTrait;

    /** @var WeakReference<NodeInterface>|null */
    public private(set) ?WeakReference $parent = null;

    public private(set) ContextStack $contextStack;

    /**
     * @param NodeAttributeInterface[] $attributes
     * @param array<string,mixed> $meta
     * @param string[] $tags
     */
    public function __construct(
        public readonly string $name,
        public readonly NodeOrigin $origin,
        public protected(set) array $attributes,
        ?NodeInterface $parent = null,
        array $meta = [],
        array $tags = [],
    ) {
        if ($parent !== null) {
            $this->setParent($parent);
        } else {
            $this->initializeContext($this, null);
        }

        $this->meta = $meta;
        $this->tags = $tags;
    }

    /**
     * Initializes the context stack for the node. Should be called when the node is created or when its parent is set/changed.
     * So, in theory you should not call this method because it's handled internally by the constructor and setParent method.
     */
    protected function initializeContext(NodeInterface $node, ?ContextStack $parentContextStack): void
    {
        if ($parentContextStack === null) {
            $this->contextStack = new ContextStack([new NodeContext($node)]);
            return;
        }

        $this->contextStack = $parentContextStack->push(new NodeContext($node));
    }

    public function applyFormatting(Formatter $formatter, string $style, bool $recursive = false): self
    {
        $this->contextStack->treeContext[ContextStack::STYLE] = $style;
        $formatter->applyFormatters($this, $style);

        if ($recursive) {
            throw new LogicException("Recursive formatting is not implemented yet.");
        }

        return $this;
    }

    public function addAttribute(NodeAttributeInterface $attribute, Placement $placement = Placement::After, int $offset = -1): self
    {
        if ($offset < 0) {
            $offset = count($this->attributes) + $offset + 1;
        }

        $offset = match ($placement) {
            Placement::Before => $offset,
            Placement::After => $offset + 1,
        };

        array_splice($this->attributes, $offset, 0, [$attribute]);

        return $this;
    }

    /** @return NodeAttributeInterface[] */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getContextStack(): ContextStack
    {
        return $this->contextStack;
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
        $this->initializeContext($this, $parent->getContextStack());

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
