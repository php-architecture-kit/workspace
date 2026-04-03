<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Processing\Event\Matching\Contract;

interface SequenceBasedEvent extends MatchingEvent
{
    public function sequenceName(): string;
}
