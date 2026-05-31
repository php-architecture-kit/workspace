<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute;

use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeAttributeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\Placement;
use PhpArchitecture\Parser\Foundation\Shared\Meta\MetaInterface;
use PhpArchitecture\Parser\Foundation\Shared\Meta\MetaTrait;
use PhpArchitecture\Parser\Foundation\Shared\Tags\TagsTrait;

/**
 * @template T of NodeInterface
 */
class GroupAttribute implements NodeAttributeInterface, MetaInterface
{
    use MetaTrait;
    use TagsTrait;

    /**
     * @param T[] $nodes
     * @param array<string,mixed> $meta
     * @param string[] $tags
     */
    public function __construct(
        public string $name,
        public array $nodes,
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

    public function addNode(NodeInterface $node, Placement $placement = Placement::After, int $offset = -1): void
    {
        if ($offset < 0) {
            $offset = count($this->nodes) + $offset + 1;
        }

        $offset = match ($placement) {
            Placement::Before => $offset,
            Placement::After => $offset + 1,
        };

        array_splice($this->nodes, $offset, 0, [$node]);
    }

    /**
     * @param callable(NodeInterface):bool $filter
     * @return T[]
     */
    public function getNodes(?callable $filter = null): array
    {
        if ($filter === null) {
            return $this->nodes;
        }

        return array_filter($this->nodes, $filter);
    }

    public function removeNodeByOffset(int $offset): self
    {
        array_splice($this->nodes, $offset, 1);

        return $this;
    }

    /**
     * @param callable(NodeInterface):bool $filter true - stay, false - remove
     */
    public function removeNodeByFilter(callable $filter): self
    {
        $this->nodes = array_filter($this->nodes, $filter);

        return $this;
    }

    public function withParent(NodeInterface $parent): static
    {
        foreach ($this->nodes as $node) {
            $node->setParent($parent);
        }
        return $this;
    }

    public function __toString(): string
    {
        return implode('', array_map(
            static fn(NodeInterface $node) => $node->__toString(),
            $this->nodes,
        ));
    }
}
