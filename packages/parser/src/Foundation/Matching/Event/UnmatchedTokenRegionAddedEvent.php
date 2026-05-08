<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Matching\Event;

use PhpArchitecture\Parser\Foundation\Matching\Event\Contract\MatchingEvent;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\TokenRegion;

final readonly class UnmatchedTokenRegionAddedEvent implements MatchingEvent
{
    public function __construct(
        public TokenRegion $region
    ) {}
}
