<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Processing\Event\Tokenization;

use PhpArchitecture\Parser\Processing\Event\Tokenization\Contract\TokenizationEvent;
use PhpArchitecture\Parser\Processing\Event\Tokenization\Contract\TokenRegionBasedEvent;
use PhpArchitecture\Parser\Processing\Model\Tokenization\TokenRegion;

class TokenRegionRemovedEvent implements TokenizationEvent, TokenRegionBasedEvent
{
    public function __construct(
        public readonly TokenRegion $region
    ) {}

    public function name(): string
    {
        return $this->region->name;
    }
}
