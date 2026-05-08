<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Tokenization\Event;

use PhpArchitecture\Parser\Foundation\Tokenization\Event\Contract\TokenizationEvent;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\Contract\TokenRegionBasedEvent;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\TokenRegion;

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
