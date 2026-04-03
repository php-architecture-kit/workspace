<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Processing\Event\Matching;

use PhpArchitecture\Parser\Processing\Event\Matching\Contract\SequenceBasedEvent;
use PhpArchitecture\Parser\Processing\Model\Matching\MatchedSequence;

final readonly class SequenceMatchedEvent implements SequenceBasedEvent
{
    public function __construct(
        public MatchedSequence $sequence
    ) {}

    public function sequenceName(): string
    {
        return $this->sequence->name;
    }
}
