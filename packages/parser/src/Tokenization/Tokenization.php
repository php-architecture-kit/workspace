<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tokenization;

use PhpArchitecture\Parser\Shared\Meta\MetaTrait;
use PhpArchitecture\Parser\Tokenization\Event\TokenAddedEvent;
use PhpArchitecture\Parser\Tokenization\Event\TokenizationFinishedEvent;
use PhpArchitecture\Parser\Tokenization\Event\TokenizationStartedEvent;
use PhpArchitecture\Parser\Tokenization\Event\TokenMatchedEvent;
use PhpArchitecture\Parser\Tokenization\Event\TokenRegionEndedEvent;
use PhpArchitecture\Parser\Tokenization\Event\TokenRegionReturnEvent;
use PhpArchitecture\Parser\Tokenization\Event\TokenRegionStartedEvent;
use PhpArchitecture\Parser\Tokenization\Model\PatternLibrary;
use PhpArchitecture\Parser\Tokenization\Model\Token;
use PhpArchitecture\Parser\Tokenization\Model\TokenRegion;

class Tokenization
{
    use MetaTrait;

    public int $currentRow = 1;
    public int $currentColumn = 1;
    public TokenRegion $currentRegion;
    public TokenizationEventDispatcher $dispatcher;
    public PatternLibrary $patternLibrary;
    public bool $forceTokenizationEnd = false;

    /** @var array<string,PatternLibrary> */
    public array $regionToPatternLibraryMap = [];

    /** @var array<string,TokenizationEventDispatcher> */
    public array $regionToEventDispatcherMap = [];

    public readonly TokenRegion $output;

    public function __construct(
        public readonly string $rootName,
        public readonly bool $applyBofEof = true,
        public readonly int $chunkSize = 8192,
        public readonly int $safeMargin = 1024,
    ) {
        $this->output = TokenRegion::new($this->rootName);
        $this->currentRegion = $this->output;
    }

    public function addToken(Token $token): void
    {
        $this->dispatcher->dispatchEvent(new TokenMatchedEvent($token));
        $this->currentRegion->stream->add($token);
        $this->dispatcher->dispatchEvent(new TokenAddedEvent($token));
    }

    public function addRegion(TokenRegion $region): void
    {
        $this->currentRegion->stream->add($region);
        $this->currentRegion = $region;
        $this->patternLibrary = $this->regionToPatternLibraryMap[$region->name] ?? $this->patternLibrary;
        $this->dispatcher = $this->regionToEventDispatcherMap[$region->name] ?? $this->dispatcher;
        $this->dispatcher->dispatchEvent(new TokenRegionStartedEvent($region));
    }

    public function escapeToRegion(TokenRegion $region): void
    {
        $this->dispatcher->dispatchEvent(new TokenRegionEndedEvent($this->currentRegion));
        $this->currentRegion = $region;
        $this->patternLibrary = $this->regionToPatternLibraryMap[$region->name] ?? $this->patternLibrary;
        $this->dispatcher = $this->regionToEventDispatcherMap[$region->name] ?? $this->dispatcher;
        $this->dispatcher->dispatchEvent(new TokenRegionReturnEvent($this->currentRegion));
    }

    public function forceEscape(): void
    {
        $this->dispatcher->dispatchEvent(new TokenRegionEndedEvent($this->currentRegion));
        $this->forceTokenizationEnd = true;
    }

    public function markTokenizationStarted(): void
    {
        $this->dispatcher->dispatchEvent(new TokenizationStartedEvent());
    }

    public function markTokenizationFinished(): void
    {
        $this->dispatcher->dispatchEvent(new TokenizationFinishedEvent($this->forceTokenizationEnd));
    }
}
