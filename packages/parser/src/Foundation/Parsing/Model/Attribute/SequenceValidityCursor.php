<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute;

use InvalidArgumentException;
use PhpArchitecture\Parser\Foundation\Matching\Model\NestedSequence;
use PhpArchitecture\Parser\Foundation\Matching\Model\Sequence;
use PhpArchitecture\Parser\Foundation\Matching\Model\SequenceNode;

final class SequenceValidityCursor
{
    /** @var CursorLevel[] */
    private array $stack;

    public function __construct(NestedSequence $sequence)
    {
        $this->stack = [new CursorLevel($sequence, null, 0, 0)];
    }

    /**
     * Validates that $name is allowed at the current position, then advances.
     *
     * @throws InvalidArgumentException when $name is not valid at the current position
     */
    public function advance(string $name): void
    {
        $newStack = $this->tryAdvance($name, $this->stack);

        if ($newStack === null) {
            $valid = implode(', ', $this->getValidNextNames());
            throw new InvalidArgumentException(
                "Unexpected attribute '{$name}' in grouped sequence. Valid at current position: [{$valid}].",
            );
        }

        $this->stack = $newStack;
    }

    /**
     * Returns all attribute names that are structurally valid at the current position.
     * An empty result means the sequence is complete (no more attributes expected).
     *
     * @return string[]
     */
    public function getValidNextNames(): array
    {
        return array_values(array_unique($this->collectValid($this->stack)));
    }

    /**
     * Returns true if the sequence can legitimately end at the current position.
     */
    public function canComplete(): bool
    {
        return $this->checkCompletable($this->stack);
    }

    /**
     * Finds the NestedSequence with the given anchorName within a compiled Sequence
     * and returns a cursor positioned at its start.
     */
    public static function fromSequence(Sequence $sequence, string $anchorName): self
    {
        $nested = self::findNestedByAnchor($sequence->nodes, $anchorName);

        if ($nested === null) {
            throw new InvalidArgumentException(
                "No NestedSequence with anchorName '{$anchorName}' found in sequence '{$sequence->name}'.",
            );
        }

        return new self($nested);
    }

    /**
     * Returns new stack after consuming $name, or null if $name is not valid at current position.
     *
     * @param CursorLevel[] $stack
     * @return CursorLevel[]|null
     */
    private function tryAdvance(string $name, array $stack): ?array
    {
        if (empty($stack)) {
            return null;
        }

        $topIdx = array_key_last($stack);
        $level = $stack[$topIdx];
        $seq = $level->nestedSequence;

        $altsToTry = $level->activeAlternative !== null
            ? [$level->activeAlternative => $seq->alternativeSequences[$level->activeAlternative]]
            : $seq->alternativeSequences;

        foreach ($altsToTry as $altIdx => $alternative) {
            $result = $this->tryAdvanceInAlternative(
                $name,
                $stack,
                $topIdx,
                $altIdx,
                $alternative,
                $level->positionInAlternative,
                $level->completedIterations,
            );

            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }

    /**
     * @param CursorLevel[] $stack
     * @param (NestedSequence|SequenceNode)[] $alternative
     * @return CursorLevel[]|null
     */
    private function tryAdvanceInAlternative(
        string $name,
        array $stack,
        int $topIdx,
        int $altIdx,
        array $alternative,
        int $startPos,
        int $completedIterations,
    ): ?array {
        $i = $startPos;

        while ($i < count($alternative)) {
            $node = $alternative[$i];

            if ($node instanceof SequenceNode && !$node->isLookahead && !$node->isLookbehind && !$node->isNegation) {
                $matches = $node->anchorName !== null
                    ? $name === $node->anchorName
                    : in_array($name, $node->alternatives, true);

                if ($matches) {
                    $newLevel = new CursorLevel(
                        $stack[$topIdx]->nestedSequence,
                        $altIdx,
                        $i + 1,
                        $completedIterations,
                    );
                    $newStack = array_slice($stack, 0, $topIdx);
                    $newStack[] = $newLevel;
                    return $newStack;
                }

                if ($node->min >= 1) {
                    return null;
                }

                $i++;
                continue;
            }

            if ($node instanceof NestedSequence && !$node->isLookahead && !$node->isLookbehind) {
                $parentLevelUpdated = new CursorLevel(
                    $stack[$topIdx]->nestedSequence,
                    $altIdx,
                    $i,
                    $completedIterations,
                );
                $childLevel = new CursorLevel($node, null, 0, 0);
                $childStack = array_slice($stack, 0, $topIdx);
                $childStack[] = $parentLevelUpdated;
                $childStack[] = $childLevel;

                $result = $this->tryAdvance($name, $childStack);
                if ($result !== null) {
                    return $result;
                }

                if ($node->min >= 1) {
                    return null;
                }

                $i++;
                continue;
            }

            $i++;
        }

        // End of alternative: (a) restart this sequence, (b) exit to parent
        $seq = $stack[$topIdx]->nestedSequence;
        $newCompletedIterations = $completedIterations + 1;

        if ($newCompletedIterations < $seq->max) {
            $restartedLevel = new CursorLevel($seq, null, 0, $newCompletedIterations);
            $restartStack = array_slice($stack, 0, $topIdx);
            $restartStack[] = $restartedLevel;

            $result = $this->tryAdvance($name, $restartStack);
            if ($result !== null) {
                return $result;
            }
        }

        if ($topIdx > 0) {
            $parentIdx = $topIdx - 1;
            $parentLevel = $stack[$parentIdx];
            $parentSeq = $parentLevel->nestedSequence;

            $parentAlts = $parentLevel->activeAlternative !== null
                ? [$parentLevel->activeAlternative => $parentSeq->alternativeSequences[$parentLevel->activeAlternative]]
                : $parentSeq->alternativeSequences;

            foreach ($parentAlts as $pAltIdx => $pAlternative) {
                $exitStack = array_slice($stack, 0, $parentIdx);
                $exitStack[] = new CursorLevel(
                    $parentSeq,
                    $pAltIdx,
                    $parentLevel->positionInAlternative + 1,
                    $parentLevel->completedIterations,
                );

                $result = $this->tryAdvanceInAlternative(
                    $name,
                    $exitStack,
                    $parentIdx,
                    $pAltIdx,
                    $pAlternative,
                    $parentLevel->positionInAlternative + 1,
                    $parentLevel->completedIterations,
                );

                if ($result !== null) {
                    return $result;
                }
            }
        }

        return null;
    }

    /**
     * @param CursorLevel[] $stack
     * @return string[]
     */
    private function collectValid(array $stack): array
    {
        if (empty($stack)) {
            return [];
        }

        $topIdx = array_key_last($stack);
        $level = $stack[$topIdx];
        $seq = $level->nestedSequence;

        $altsToCheck = $level->activeAlternative !== null
            ? [$level->activeAlternative => $seq->alternativeSequences[$level->activeAlternative]]
            : $seq->alternativeSequences;

        $names = [];
        foreach ($altsToCheck as $alternative) {
            $names = array_merge(
                $names,
                $this->collectValidFromAlternative(
                    $alternative,
                    $level->positionInAlternative,
                    $stack,
                    $topIdx,
                    $level->completedIterations,
                ),
            );
        }

        return $names;
    }

    /**
     * @param (NestedSequence|SequenceNode)[] $alternative
     * @param CursorLevel[] $stack
     * @return string[]
     */
    private function collectValidFromAlternative(
        array $alternative,
        int $startPos,
        array $stack,
        int $topIdx,
        int $completedIterations,
    ): array {
        $names = [];
        $i = $startPos;

        while ($i < count($alternative)) {
            $node = $alternative[$i];

            if ($node instanceof SequenceNode && !$node->isLookahead && !$node->isLookbehind && !$node->isNegation) {
                $names = array_merge(
                    $names,
                    $node->anchorName !== null ? [$node->anchorName] : $node->alternatives,
                );
                if ($node->min >= 1) {
                    return $names;
                }
                $i++;
                continue;
            }

            if ($node instanceof NestedSequence && !$node->isLookahead && !$node->isLookbehind) {
                $names = array_merge($names, $node->getFirstValidNodeNodeNames());
                if ($node->min >= 1) {
                    return $names;
                }
                $i++;
                continue;
            }

            $i++;
        }

        // End of alternative: use getFirstValidNodeNodeNames() for restart (avoids infinite loop with max=PHP_INT_MAX)
        $seq = $stack[$topIdx]->nestedSequence;
        $newCompletedIterations = $completedIterations + 1;

        if ($newCompletedIterations < $seq->max) {
            $names = array_merge($names, $seq->getFirstValidNodeNodeNames());
        }

        if ($topIdx > 0) {
            $names = array_merge($names, $this->collectValid(array_slice($stack, 0, $topIdx)));
        }

        return $names;
    }

    /**
     * @param CursorLevel[] $stack
     */
    private function checkCompletable(array $stack): bool
    {
        if (empty($stack)) {
            return true;
        }

        $topIdx = array_key_last($stack);
        $level = $stack[$topIdx];
        $seq = $level->nestedSequence;

        // No iteration in progress: we can stop if completed iterations satisfy min
        if ($level->activeAlternative === null && $level->completedIterations >= $seq->min) {
            return $topIdx === 0 || $this->checkCompletable(array_slice($stack, 0, $topIdx));
        }

        $altsToCheck = $level->activeAlternative !== null
            ? [$level->activeAlternative => $seq->alternativeSequences[$level->activeAlternative]]
            : $seq->alternativeSequences;

        foreach ($altsToCheck as $alternative) {
            if ($this->isAlternativeCompletable($alternative, $level->positionInAlternative)) {
                if ($topIdx === 0) {
                    return true;
                }
                return $this->checkCompletable(array_slice($stack, 0, $topIdx));
            }
        }

        return false;
    }

    /** @param (NestedSequence|SequenceNode)[] $alternative */
    private function isAlternativeCompletable(array $alternative, int $startPos): bool
    {
        for ($i = $startPos; $i < count($alternative); $i++) {
            if ($alternative[$i]->min >= 1) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param (NestedSequence|SequenceNode)[] $nodes
     */
    private static function findNestedByAnchor(array $nodes, string $anchorName): ?NestedSequence
    {
        foreach ($nodes as $node) {
            if (!$node instanceof NestedSequence) {
                continue;
            }

            if ($node->anchorName === $anchorName) {
                return $node;
            }

            $found = self::findNestedByAnchor(
                array_merge(...array_map(
                    static fn(array $alt): array => $alt,
                    $node->alternativeSequences,
                )),
                $anchorName,
            );

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }
}
