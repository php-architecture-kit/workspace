<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Tokenization\Event;

use PhpArchitecture\Parser\Foundation\Tokenization\Event\Contract\TokenBasedEvent;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\Contract\TokenizationEvent;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\Token;

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
