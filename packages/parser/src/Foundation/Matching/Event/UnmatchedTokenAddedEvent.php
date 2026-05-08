<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Matching\Event;

use PhpArchitecture\Parser\Foundation\Matching\Event\Contract\MatchingEvent;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\Token;

final readonly class UnmatchedTokenAddedEvent implements MatchingEvent
{
    public function __construct(
        public Token $token
    ) {}
}
