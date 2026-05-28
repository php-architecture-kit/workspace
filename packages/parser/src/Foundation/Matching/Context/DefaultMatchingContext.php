<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Matching\Context;

use PhpArchitecture\Parser\Foundation\Matching\Contract\MatchingContext;
use PhpArchitecture\Parser\Foundation\Matching\Event\Contract\MatchingEventListener;
use PhpArchitecture\Parser\Foundation\Matching\Event\MatchingFinishedEvent;
use PhpArchitecture\Parser\Foundation\Matching\Event\MatchingStartedEvent;
use PhpArchitecture\Parser\Foundation\Matching\Event\SequenceAddedEvent;
use PhpArchitecture\Parser\Foundation\Matching\Event\SequenceMatchedEvent;
use PhpArchitecture\Parser\Foundation\Matching\Event\UnmatchedTokenAddedEvent;
use PhpArchitecture\Parser\Foundation\Matching\Event\UnmatchedTokenRegionAddedEvent;
use PhpArchitecture\Parser\Foundation\Matching\Model\MatchedRegion;
use PhpArchitecture\Parser\Foundation\Matching\Model\MatchedSequence;
use PhpArchitecture\Parser\Foundation\Matching\Model\SequenceLibrary;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\Token;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\TokenRegion;
use PhpArchitecture\Parser\Foundation\Shared\Meta\MetaTrait;

class DefaultMatchingContext implements MatchingContext
{
    use MetaTrait;
    private MatchingEventDispatcher $dispatcher;
    private MatchedRegion $output;

    /**
     * @param string[] $tags
     * @param array<string,mixed> $meta
     */
    public function __construct(
        private readonly string $regionName,
        private readonly SequenceLibrary $sequenceLibrary,
        array $tags = [],
        array $meta = [],
    ) {
        $this->output = new MatchedRegion($this->regionName, [], $meta, $tags);
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
