<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Processing\Event\Matching;

use PhpArchitecture\Parser\Processing\Event\Matching\Contract\MatchingEvent;
use PhpArchitecture\Parser\Processing\Model\Tokenization\Token;

final readonly class UnmatchedTokenAddedEvent implements MatchingEvent
{
    public function __construct(
        public Token $token
    ) {}
}
