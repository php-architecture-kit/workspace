<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Matching\Event;

use PhpArchitecture\Parser\Foundation\Matching\Event\Contract\SequenceBasedEvent;
use PhpArchitecture\Parser\Foundation\Matching\Model\MatchedSequence;

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
