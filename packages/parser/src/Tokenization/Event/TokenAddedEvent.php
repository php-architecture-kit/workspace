<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tokenization\Event;

use PhpArchitecture\Parser\Tokenization\Event\Contract\TokenBasedEvent;
use PhpArchitecture\Parser\Tokenization\Event\Contract\TokenizationEvent;
use PhpArchitecture\Parser\Tokenization\Model\Token;

class TokenAddedEvent implements TokenizationEvent, TokenBasedEvent
{
    public function __construct(
        public readonly Token $token
    ) {}

    public function name(): string
    {
        return $this->token->name;
    }
}
