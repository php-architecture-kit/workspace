<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Matching\Event\Contract;

interface SequenceBasedEvent extends MatchingEvent
{
    public function sequenceName(): string;
}
