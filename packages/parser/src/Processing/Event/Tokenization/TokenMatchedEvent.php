<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Processing\Event\Tokenization;

use PhpArchitecture\Parser\Processing\Event\Tokenization\Contract\TokenBasedEvent;
use PhpArchitecture\Parser\Processing\Event\Tokenization\Contract\TokenizationEvent;
use PhpArchitecture\Parser\Processing\Model\Tokenization\Token;

class TokenMatchedEvent implements TokenizationEvent, TokenBasedEvent
{
    public function __construct(
        public readonly Token $token
    ) {}

    public function name(): string
    {
        return $this->token->name;
    }
}
