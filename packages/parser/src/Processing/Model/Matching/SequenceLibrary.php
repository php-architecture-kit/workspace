<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Processing\Model\Matching;

final class SequenceLibrary
{
    /** @var array<string,Sequence> */
    public array $sequences = [];

    /** @var array<string,int> */
    public array $sequenceIndexMap = [];

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
}
