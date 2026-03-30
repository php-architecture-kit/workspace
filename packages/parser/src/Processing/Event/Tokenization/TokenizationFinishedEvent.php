<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Processing\Event\Tokenization;

use PhpArchitecture\Parser\Processing\Event\Tokenization\Contract\TokenizationEvent;

class TokenizationFinishedEvent implements TokenizationEvent
{
    public function __construct(
        public readonly bool $forced
    ) {}
}
