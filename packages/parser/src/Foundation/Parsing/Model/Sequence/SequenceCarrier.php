<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Parsing\Model\Sequence;

use InvalidArgumentException;
use LogicException;
use OutOfRangeException;
use PhpArchitecture\Parser\Foundation\Matching\Model\NestedSequence;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeAttributeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\SequenceAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\SequenceValidityCursor;

/**
 * The grammar-validated sequence machinery: a validity cursor, content/structural
 * classification (via the `/c` marker {@see SequenceAttribute::CONTENT_TAG}), and
 * unit add/remove/read.
 *
 * Shared by {@see SequenceAttribute} (an anchor-named nested `/g` sub-run, embedded
 * as an attribute) and — in a later refactor stage — by the Sequence-origin shape
 * node. The using class supplies {@see attributesOwner()} so children get the right
 * parent (the SequenceAttribute's owning node, or the node itself).
 */
trait SequenceCarrier
{
    private ?SequenceValidityCursor $validityCursor = null;
    private ?NestedSequence $nestedSequence = null;

    /** @var int[] flat offsets of content attributes within $attributes */
    private array $contentOffsets = [];

    /** @var array<string, callable(): NodeAttributeInterface> */
    private array $autoFactories = [];

    /**
     * The node that owns these attributes (children get it as parent), or null.
     */
    abstract protected function attributesOwner(): ?NodeInterface;

    /**
     * Appends an attribute without structural validation.
     * Used by the parser; does not update contentOffsets.
     */
    public function addAttribute(NodeAttributeInterface $attr): void
    {
        $this->validityCursor?->advance($attr->getName());

        $owner = $this->attributesOwner();
        $this->attributes[] = $owner ? $attr->withParent($owner) : $attr;
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

        $owner = $this->attributesOwner();
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
            $this->attributes[] = $owner ? $auto->withParent($owner) : $auto;

            $validNext = $this->validityCursor->getValidNextNames();
        }

        $this->validityCursor->advance($content->getName());
        $this->contentOffsets[] = count($this->attributes);
        $this->attributes[] = $owner ? $content->withParent($owner) : $content;
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
            // Last unit owns its trailing structural block, up to the array end —
            // symmetric with unit 0 owning the leading block from index 0.
            $start = $this->contentOffsets[$contentIndex - 1] + 1;
            $end   = count($this->attributes) - 1;
        } else {
            // Middle unit: preceding separator + content + following separator
            $start = $this->contentOffsets[$contentIndex - 1] + 1;
            $end   = $this->contentOffsets[$contentIndex + 1] - 1;
        }

        return array_values(array_slice($this->attributes, $start, $end - $start + 1));
    }

    /**
     * @param array<string, callable(): NodeAttributeInterface> $autoFactories
     *   Factories for structural attributes (trivia, comma, etc.) keyed by attribute name.
     *   Used by addUnit() for auto-insertion and by removeUnit() for unit boundary
     *   detection. Content vs structural classification is taken from the `/c` content
     *   marker when present; otherwise it falls back to "not an autoFactory key" — see
     *   isContentByClassification().
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
        $useMarker = $this->hasContentMarkers();
        foreach ($this->attributes as $idx => $attr) {
            $this->validityCursor->advance($attr->getName());
            if ($this->isContentByClassification($attr, $useMarker)) {
                $this->contentOffsets[] = $idx;
            }
        }

        return $this;
    }

    /**
     * Classifies an attribute as content (vs structural).
     *
     * Preferred: the `/c` content marker (SequenceAttribute::CONTENT_TAG), stamped
     * during compilation and carried onto the parsed attribute. Fallback (for
     * hand-assembled attributes that carry no marker): an attribute is content iff
     * its name is NOT a registered structural autoFactory key.
     */
    private function isContentByClassification(NodeAttributeInterface $attr, bool $useMarker): bool
    {
        return $useMarker
            ? $this->isContentAttribute($attr)
            : !array_key_exists($attr->getName(), $this->autoFactories);
    }

    private function isContentAttribute(NodeAttributeInterface $attr): bool
    {
        return method_exists($attr, 'hasTag') && $attr->hasTag(SequenceAttribute::CONTENT_TAG);
    }

    private function hasContentMarkers(): bool
    {
        foreach ($this->attributes as $attr) {
            if ($this->isContentAttribute($attr)) {
                return true;
            }
        }

        return false;
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
            $useMarker = $this->hasContentMarkers();
            foreach ($this->attributes as $idx => $attr) {
                if ($this->isContentByClassification($attr, $useMarker)) {
                    $this->contentOffsets[] = $idx;
                }
            }
        }
    }
}
