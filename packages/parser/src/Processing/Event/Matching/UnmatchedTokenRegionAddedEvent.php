<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Processing\Event\Matching;

use PhpArchitecture\Parser\Processing\Event\Matching\Contract\MatchingEvent;
use PhpArchitecture\Parser\Processing\Model\Tokenization\TokenRegion;

final readonly class UnmatchedTokenRegionAddedEvent implements MatchingEvent
{
    public function __construct(
        public TokenRegion $region
    ) {}
}
