<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Matching\Contract;

use PhpArchitecture\Parser\Foundation\Matching\Event\Contract\MatchingEvent;
use PhpArchitecture\Parser\Foundation\Matching\Event\Contract\MatchingEventListener;
use PhpArchitecture\Parser\Foundation\Matching\Model\MatchedRegion;
use PhpArchitecture\Parser\Foundation\Matching\Model\MatchedSequence;
use PhpArchitecture\Parser\Foundation\Matching\Model\SequenceLibrary;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\Token;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\TokenRegion;
use PhpArchitecture\Parser\Foundation\Shared\Meta\MetaInterface;

interface MatchingContext extends MetaInterface
{
    public function getOutput(): MatchedRegion;
    public function getSequenceLibrary(): SequenceLibrary;

    public function addMatchedSequence(MatchedSequence $sequence): void;
    public function addUnmatchedToken(Token $token): void;
    public function addUnmatchedTokenRegion(TokenRegion $region): void;

    public function markMatchingStarted(): void;
    public function markMatchingFinished(): void;

    /** @param class-string<MatchingEvent> $eventClassName */
    public function registerEventListener(
        MatchingEventListener $listener,
        string $eventClassName,
        ?string $onlyOnRule = null
    ): void;
}
