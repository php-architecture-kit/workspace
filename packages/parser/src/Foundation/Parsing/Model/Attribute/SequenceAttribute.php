<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute;

use InvalidArgumentException;
use LogicException;
use OutOfRangeException;
use PhpArchitecture\Parser\Foundation\Matching\Model\NestedSequence;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeAttributeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Shared\Meta\MetaInterface;
use PhpArchitecture\Parser\Foundation\Shared\Meta\MetaTrait;
use PhpArchitecture\Parser\Foundation\Shared\Tags\TagsTrait;

class SequenceAttribute implements NodeAttributeInterface, MetaInterface
{
    use MetaTrait;
    use TagsTrait;

    public const TAG = 'SequenceAttribute';
    public const DEFAULT_NAME = 'sequence';
    public const ANCHOR_NAME_META_KEY = 'sequenceAnchorName';

    /** @var NodeAttributeInterface[] */
    public array $attributes;
    private ?SequenceValidityCursor $validityCursor = null;
    private ?NestedSequence $nestedSequence = null;

    /** @var int[] flat offsets of content attributes within $attributes */
    private array $contentOffsets = [];

    /** @var array<string, callable(): NodeAttributeInterface> */
    private array $autoFactories = [];

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

    /**
     * Appends an attribute without structural validation.
     * Used by the parser; does not update contentOffsets.
     */
    public function addAttribute(NodeAttributeInterface $attr): void
    {
        $this->validityCursor?->advance($attr->getName());

        $this->attributes[] = $this->parent ? $attr->withParent($this->parent) : $attr;
    }

    /**
     * Adds a content attribute, auto-inserting any structural attributes
     * (trivia, comma, etc.) required by the sequence before the content.
     *
     * Requires withValidSequence() with autoFactories to have been called first.
     *
     * @throws LogicException when no validity cursor is set or a factory is missing
     * @throws InvalidArgumentException when content cannot be placed at any position
     */
    public function addUnit(NodeAttributeInterface $content): void
    {
        if ($this->validityCursor === null) {
            throw new LogicException(
                "Cannot call addUnit() without a validity cursor. Call withValidSequence() first.",
            );
        }

        $validNext = $this->validityCursor->getValidNextNames();

        while (!in_array($content->getName(), $validNext, true)) {
            if (empty($validNext)) {
                throw new InvalidArgumentException(
                    "Sequence is complete, cannot add '{$content->getName()}'.",
                );
            }

            $next = $validNext[0];

            if (!array_key_exists($next, $this->autoFactories)) {
                throw new LogicException(
                    "No factory registered for structural attribute '{$next}'. "
                    . "Register it via the \$autoFactories parameter of withValidSequence().",
                );
            }

            $auto = ($this->autoFactories[$next])();
            $this->validityCursor->advance($auto->getName());
            $this->attributes[] = $this->parent ? $auto->withParent($this->parent) : $auto;

            $validNext = $this->validityCursor->getValidNextNames();
        }

        $this->validityCursor->advance($content->getName());
        $this->contentOffsets[] = count($this->attributes);
        $this->attributes[] = $this->parent ? $content->withParent($this->parent) : $content;
    }

    /**
     * Removes the unit at $contentIndex together with its structural attributes,
     * keeping the sequence valid. Removal strategy:
     *   - unit 0 (when N>1): removes C₀ + the following structural block
     *   - unit i>0: removes the preceding structural block + Cᵢ
     *
     * @throws OutOfRangeException when contentIndex is out of bounds
     */
    public function removeUnit(int $contentIndex): void
    {
        $n = count($this->contentOffsets);

        if ($contentIndex < 0 || $contentIndex >= $n) {
            throw new OutOfRangeException(
                "Content index {$contentIndex} is out of range [0, {$n}).",
            );
        }

        $thisOffset = $this->contentOffsets[$contentIndex];

        if ($contentIndex === 0) {
            if ($n === 1) {
                $this->attributes = [];
            } else {
                // Remove C₀ + following structural block up to (not including) C₁
                array_splice($this->attributes, 0, $this->contentOffsets[1]);
            }
        } else {
            // Remove preceding structural block + Cᵢ
            $prevOffset = $this->contentOffsets[$contentIndex - 1];
            array_splice($this->attributes, $prevOffset + 1, $thisOffset - $prevOffset);
        }

        $this->rebuildAfterMutation();
    }

    public function getUnitCount(): int
    {
        return count($this->contentOffsets);
    }

    /**
     * Returns the content attribute at the given unit index.
     *
     * @throws OutOfRangeException when index is out of bounds
     */
    public function getUnitContent(int $contentIndex): NodeAttributeInterface
    {
        if (!isset($this->contentOffsets[$contentIndex])) {
            throw new OutOfRangeException(
                "Content index {$contentIndex} is out of range [0, " . count($this->contentOffsets) . ").",
            );
        }

        return $this->attributes[$this->contentOffsets[$contentIndex]];
    }

    /**
     * Returns all attributes that make up the unit at the given index:
     * the content attribute together with its surrounding structural
     * attributes (trivia, comma, etc.).
     *
     * Requires withValidSequence() to have been called first.
     *
     * @return NodeAttributeInterface[]
     * @throws OutOfRangeException when index is out of bounds
     */
    public function getUnit(int $contentIndex): array
    {
        $n = count($this->contentOffsets);

        if ($contentIndex < 0 || $contentIndex >= $n) {
            throw new OutOfRangeException(
                "Content index {$contentIndex} is out of range [0, {$n}).",
            );
        }

        if ($contentIndex === 0) {
            $start = 0;
            $end   = $n === 1
                ? count($this->attributes) - 1
                : $this->contentOffsets[1] - 1;
        } elseif ($contentIndex === $n - 1) {
            $start = $this->contentOffsets[$contentIndex - 1] + 1;
            $end   = $this->contentOffsets[$contentIndex];
        } else {
            // Middle unit: preceding separator + content + following separator
            $start = $this->contentOffsets[$contentIndex - 1] + 1;
            $end   = $this->contentOffsets[$contentIndex + 1] - 1;
        }

        return array_values(array_slice($this->attributes, $start, $end - $start + 1));
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

    /**
     * @param array<string, callable(): NodeAttributeInterface> $autoFactories
     *   Factories for structural attributes (trivia, comma, etc.) keyed by attribute name.
     *   Attributes whose names are NOT in this map are treated as content (tracked in
     *   contentOffsets). Used by addUnit() for auto-insertion and by removeUnit() for
     *   unit boundary detection.
     */
    public function withValidSequence(
        NestedSequence|SequenceValidityCursor $sequence,
        array $autoFactories = [],
    ): static {
        $this->autoFactories = $autoFactories;

        if ($sequence instanceof NestedSequence) {
            $this->nestedSequence = $sequence;
            $this->validityCursor = new SequenceValidityCursor($sequence);
        } else {
            $this->nestedSequence = null;
            $this->validityCursor = $sequence;
        }

        $this->contentOffsets = [];
        foreach ($this->attributes as $idx => $attr) {
            $this->validityCursor->advance($attr->getName());
            if (!array_key_exists($attr->getName(), $this->autoFactories)) {
                $this->contentOffsets[] = $idx;
            }
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

    private function rebuildAfterMutation(): void
    {
        if ($this->nestedSequence !== null) {
            $this->withValidSequence($this->nestedSequence, $this->autoFactories);
        } else {
            // No NestedSequence to replay from — rebuild contentOffsets by name heuristic,
            // invalidate cursor (caller must re-call withValidSequence to continue using addUnit)
            $this->validityCursor = null;
            $this->contentOffsets = [];
            foreach ($this->attributes as $idx => $attr) {
                if (!array_key_exists($attr->getName(), $this->autoFactories)) {
                    $this->contentOffsets[] = $idx;
                }
            }
        }
    }
}
