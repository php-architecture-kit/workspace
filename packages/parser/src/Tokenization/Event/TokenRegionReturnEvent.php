<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tokenization\Event;

use PhpArchitecture\Parser\Tokenization\Event\Contract\TokenizationEvent;
use PhpArchitecture\Parser\Tokenization\Model\TokenRegion;

class TokenRegionReturnEvent implements TokenizationEvent
{
    public function __construct(
        public readonly TokenRegion $region
    ) {}
}
