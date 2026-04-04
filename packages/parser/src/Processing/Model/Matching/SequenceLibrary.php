<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Processing\Model\Matching;

final class SequenceLibrary
{
    /** @var array<string,Sequence> */
    public array $sequences = [];

    /** @var array<string,int> */
    public array $sequenceIndexMap = [];

    /**
     * Index mapping sequence names to expanded list of token/tag names that can start them.
     * This is precomputed during compilation to avoid runtime recursion.
     * 
     * @var array<string,string[]>
     */
    public array $expandedFirstValidTokens = [];

    public ?Sequence $rootSequence = null;

    /** 
     * @param Sequence[] $sequences
     */
    public function __construct(
        array $sequences,
        ?Sequence $rootSequence = null,
    ) {
        $this->rootSequence = $rootSequence;
        $this->compileSequences($sequences);
        $this->buildExpandedFirstValidTokensIndex();
    }

    /** @param Sequence[] $sequences */
    private function compileSequences(array $sequences): void
    {
        $index = 0;
        usort($sequences, static fn($a, $b) => $b->priority - $a->priority);

        foreach ($sequences as $sequence) {
            $this->sequences[$sequence->name] = $sequence;
            $this->sequenceIndexMap[$sequence->name] = $index++;
        }
    }

    /**
     * Builds an index of expanded first valid tokens for each sequence.
     * This recursively resolves sequence references to actual token/tag names.
     */
    private function buildExpandedFirstValidTokensIndex(): void
    {
        foreach ($this->sequences as $sequenceName => $sequence) {
            $this->expandedFirstValidTokens[$sequenceName] = $this->expandFirstValidTokens($sequenceName, []);
        }
    }

    /**
     * Recursively expands first valid node names to actual token/tag names.
     * 
     * @param string $sequenceName
     * @param array<string> $visited Track visited sequences to prevent infinite recursion
     * @return string[]
     */
    private function expandFirstValidTokens(string $sequenceName, array $visited): array
    {
        // Prevent infinite recursion
        if (in_array($sequenceName, $visited)) {
            return [];
        }

        $sequence = $this->sequences[$sequenceName] ?? null;
        if ($sequence === null) {
            // Not a sequence, it's a token/tag name
            return [$sequenceName];
        }

        $visited[] = $sequenceName;
        $expanded = [];
        $firstValidNodes = $sequence->getFirstValidNodeNodeNames();

        foreach ($firstValidNodes as $nodeName) {
            if (isset($this->sequences[$nodeName])) {
                // It's a sequence reference, expand it recursively
                $nestedExpanded = $this->expandFirstValidTokens($nodeName, $visited);
                $expanded = array_merge($expanded, $nestedExpanded);
            } else {
                // It's a token/tag name, add it directly
                $expanded[] = $nodeName;
            }
        }

        return array_unique($expanded);
    }

    /**
     * Get expanded list of token/tag names that can start the given sequence.
     * 
     * @param string $sequenceName
     * @return string[]
     */
    public function getExpandedFirstValidTokens(string $sequenceName): array
    {
        return $this->expandedFirstValidTokens[$sequenceName] ?? [];
    }
}
