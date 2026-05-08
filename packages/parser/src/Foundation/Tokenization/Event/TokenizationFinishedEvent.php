<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Tokenization\Event;

use PhpArchitecture\Parser\Foundation\Tokenization\Event\Contract\TokenizationEvent;

class TokenizationFinishedEvent implements TokenizationEvent
{
    public function __construct(
        public readonly bool $forced
    ) {}
}
