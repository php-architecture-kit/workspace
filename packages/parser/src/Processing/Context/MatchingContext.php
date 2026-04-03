<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Processing\Context;

use PhpArchitecture\Parser\Processing\Event\Matching\Contract\MatchingEvent;
use PhpArchitecture\Parser\Processing\Event\Matching\Contract\MatchingEventListener;
use PhpArchitecture\Parser\Processing\Model\Matching\MatchedRegion;
use PhpArchitecture\Parser\Processing\Model\Matching\MatchedSequence;
use PhpArchitecture\Parser\Processing\Model\Matching\SequenceLibrary;
use PhpArchitecture\Parser\Processing\Model\Tokenization\Token;
use PhpArchitecture\Parser\Processing\Model\Tokenization\TokenRegion;
use PhpArchitecture\Parser\Shared\Meta\MetaInterface;

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
