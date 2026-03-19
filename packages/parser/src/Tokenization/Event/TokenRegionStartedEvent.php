<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tokenization\Event;

use PhpArchitecture\Parser\Tokenization\Event\Contract\TokenizationEvent;
use PhpArchitecture\Parser\Tokenization\Event\Contract\TokenRegionBasedEvent;
use PhpArchitecture\Parser\Tokenization\Model\TokenRegion;

class TokenRegionStartedEvent implements TokenizationEvent, TokenRegionBasedEvent
{
    public function __construct(
        public readonly TokenRegion $region
    ) {}

    public function name(): string
    {
        return $this->region->name;
    }
}
