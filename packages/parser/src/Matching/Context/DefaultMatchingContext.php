<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Matching\Context;

use PhpArchitecture\Parser\Processing\Context\MatchingContext;
use PhpArchitecture\Parser\Processing\Event\Matching\Contract\MatchingEventListener;
use PhpArchitecture\Parser\Processing\Event\Matching\MatchingFinishedEvent;
use PhpArchitecture\Parser\Processing\Event\Matching\MatchingStartedEvent;
use PhpArchitecture\Parser\Processing\Event\Matching\SequenceAddedEvent;
use PhpArchitecture\Parser\Processing\Event\Matching\SequenceMatchedEvent;
use PhpArchitecture\Parser\Processing\Event\Matching\UnmatchedTokenAddedEvent;
use PhpArchitecture\Parser\Processing\Event\Matching\UnmatchedTokenRegionAddedEvent;
use PhpArchitecture\Parser\Processing\Model\Matching\MatchedRegion;
use PhpArchitecture\Parser\Processing\Model\Matching\MatchedSequence;
use PhpArchitecture\Parser\Processing\Model\Matching\SequenceLibrary;
use PhpArchitecture\Parser\Processing\Model\Tokenization\Token;
use PhpArchitecture\Parser\Processing\Model\Tokenization\TokenRegion;
use PhpArchitecture\Parser\Shared\Meta\MetaTrait;

class DefaultMatchingContext implements MatchingContext
{
    use MetaTrait;
    private MatchingEventDispatcher $dispatcher;
    private MatchedRegion $output;

    public function __construct(
        private readonly string $regionName,
        private readonly SequenceLibrary $sequenceLibrary,
    ) {
        $this->output = new MatchedRegion($this->regionName, [], [], []);
        $this->dispatcher = new MatchingEventDispatcher($this);
    }

    public function getOutput(): MatchedRegion
    {
        return $this->output;
    }

    public function getSequenceLibrary(): SequenceLibrary
    {
        return $this->sequenceLibrary;
    }

    public function addMatchedSequence(MatchedSequence $sequence): void
    {
        $this->dispatchSequenceMatchedRecursively($sequence);
        $this->output->addItem($sequence);
        $this->dispatchSequenceAddedRecursively($sequence);
    }

    public function addUnmatchedToken(Token $token): void
    {
        $this->output->addItem($token);
        $this->dispatcher->dispatchEvent(new UnmatchedTokenAddedEvent($token));
    }

    public function addUnmatchedTokenRegion(TokenRegion $region): void
    {
        $this->output->addItem($region);
        $this->dispatcher->dispatchEvent(new UnmatchedTokenRegionAddedEvent($region));
    }

    public function markMatchingStarted(): void
    {
        $this->dispatcher->dispatchEvent(new MatchingStartedEvent());
    }

    public function markMatchingFinished(): void
    {
        $this->dispatcher->dispatchEvent(new MatchingFinishedEvent());
    }

    public function registerEventListener(
        MatchingEventListener $listener,
        string $eventClassName,
        ?string $onlyOnRule = null
    ): void {
        $this->dispatcher->registerEventListener($listener, $eventClassName, $onlyOnRule);
    }

    private function dispatchSequenceMatchedRecursively(MatchedSequence $sequence): void
    {
        $this->dispatcher->dispatchEvent(new SequenceMatchedEvent($sequence));

        foreach ($sequence->items as $item) {
            foreach ($item->items as $subItem) {
                if ($subItem instanceof MatchedSequence) {
                    if (isset($this->sequenceLibrary->sequences[$subItem->name])) {
                        $this->dispatchSequenceMatchedRecursively($subItem);
                    }
                }
            }
        }
    }

    private function dispatchSequenceAddedRecursively(MatchedSequence $sequence): void
    {
        $this->dispatcher->dispatchEvent(new SequenceAddedEvent($sequence));

        foreach ($sequence->items as $item) {
            foreach ($item->items as $subItem) {
                if ($subItem instanceof MatchedSequence) {
                    if (isset($this->sequenceLibrary->sequences[$subItem->name])) {
                        $this->dispatchSequenceAddedRecursively($subItem);
                    }
                }
            }
        }
    }
}
