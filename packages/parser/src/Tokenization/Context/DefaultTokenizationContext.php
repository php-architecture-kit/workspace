<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tokenization\Context;

use PhpArchitecture\Parser\Processing\Context\TokenizationContext;
use PhpArchitecture\Parser\Processing\Event\Tokenization\Contract\TokenizationEventListener;
use PhpArchitecture\Parser\Processing\Event\Tokenization\TokenAddedEvent;
use PhpArchitecture\Parser\Processing\Event\Tokenization\TokenizationFinishedEvent;
use PhpArchitecture\Parser\Processing\Event\Tokenization\TokenizationStartedEvent;
use PhpArchitecture\Parser\Processing\Event\Tokenization\TokenMatchedEvent;
use PhpArchitecture\Parser\Processing\Event\Tokenization\TokenRegionEndedEvent;
use PhpArchitecture\Parser\Processing\Event\Tokenization\TokenRegionRemovedEvent;
use PhpArchitecture\Parser\Processing\Event\Tokenization\TokenRegionReturnEvent;
use PhpArchitecture\Parser\Processing\Event\Tokenization\TokenRegionStartedEvent;
use PhpArchitecture\Parser\Processing\Event\Tokenization\TokenRemovedEvent;
use PhpArchitecture\Parser\Processing\Model\Tokenization\Pattern;
use PhpArchitecture\Parser\Processing\Model\Tokenization\PatternLibrary;
use PhpArchitecture\Parser\Processing\Model\Tokenization\Token;
use PhpArchitecture\Parser\Processing\Model\Tokenization\TokenRegion;
use PhpArchitecture\Parser\Shared\Meta\MetaTrait;

class DefaultTokenizationContext implements TokenizationContext
{
    use MetaTrait;

    private TokenRegion $currentRegion;
    private TokenizationEventDispatcher $dispatcher;
    private PatternLibrary $patternLibrary;
    private bool $forceTokenizationEnd = false;
    private bool $retryLastTokenTokenization = false;
    private ?Token $tokenToRemove = null;
    private readonly TokenRegion $output;

    /** 
     * @param array<string,PatternLibrary> $regionToPatternLibraryMap
     * @param array<string,TokenizationEventDispatcher> $regionToEventDispatcherMap
     */
    public function __construct(
        public readonly string $rootName,
        public readonly bool $applyBofEof = true,
        public readonly int $chunkSize = 8192,
        public readonly int $safeMargin = 1024,
        public readonly int $lastTokenAddingRetryLimit = 3,
        private array $regionToPatternLibraryMap = [],
        private array $regionToEventDispatcherMap = [],
    ) {
        $this->output = TokenRegion::new($this->rootName);
        $this->currentRegion = $this->output;

        $this->patternLibrary = $this->regionToPatternLibraryMap[$this->rootName]
            ?? new PatternLibrary([]);
        $this->dispatcher = $this->regionToEventDispatcherMap[$this->rootName]
            ?? new TokenizationEventDispatcher($this);
    }

    public function addToken(Token $token): bool
    {
        $this->retryLastTokenTokenization = false;

        $this->dispatcher->dispatchEvent(new TokenMatchedEvent($token));
        $this->currentRegion->stream->add($token);
        $this->dispatcher->dispatchEvent(new TokenAddedEvent($token));
        
        // Check if this token should be removed after being added
        if ($this->tokenToRemove !== null && $this->tokenToRemove === $token) {
            $lastOffset = $this->currentRegion->stream->lastOffset();
            if ($lastOffset !== null) {
                $lastToken = $this->currentRegion->stream->get($lastOffset);
                if ($lastToken === $token) {
                    $this->currentRegion->stream->remove($lastOffset);
                    $this->dispatcher->dispatchEvent(new TokenRemovedEvent($token));
                    // Request lexer to retry tokenization from the same position
                    $this->retryLastTokenTokenization();
                }
            }
            $this->tokenToRemove = null;
        }

        return !$this->retryLastTokenTokenization;
    }

    public function removeLastToken(Token $token): bool
    {
        $lastOffset = $this->currentRegion->stream->lastOffset();
        if ($lastOffset !== null) {
            $lastToken = $this->currentRegion->stream->get($lastOffset);

            if ($lastToken === $token) {
                // Token found in current region - remove it immediately
                $this->currentRegion->stream->remove($lastOffset);
                $this->dispatcher->dispatchEvent(new TokenRemovedEvent($lastToken));
                return true;
            }
        }

        // Token not found in current region - mark it for removal after it's added
        $this->tokenToRemove = $token;
        return false;
    }

    public function addRegion(TokenRegion $region): bool
    {
        $this->currentRegion->stream->add($region);
        $this->currentRegion = $region;
        $this->patternLibrary = $this->regionToPatternLibraryMap[$region->name] ?? $this->patternLibrary;
        $this->dispatcher = $this->regionToEventDispatcherMap[$region->name] ?? $this->dispatcher;
        $this->dispatcher->dispatchEvent(new TokenRegionStartedEvent($region));

        return true;
    }

    public function removeLastRegion(TokenRegion $region): bool
    {
        $lastOffset = $this->currentRegion->stream->lastOffset();
        if ($lastOffset !== null) {
            $lastRegion = $this->currentRegion->stream->get($lastOffset);

            if ($lastRegion !== $region) {
                return false;
            }

            $this->currentRegion->stream->remove($lastOffset);
            $this->dispatcher->dispatchEvent(new TokenRegionRemovedEvent($lastRegion));
        }

        return false;
    }

    public function escapeToRegion(TokenRegion $region): void
    {
        $this->dispatcher->dispatchEvent(new TokenRegionEndedEvent($this->currentRegion));
        $this->currentRegion = $region;
        $this->patternLibrary = $this->regionToPatternLibraryMap[$region->name] ?? $this->patternLibrary;
        $this->dispatcher = $this->regionToEventDispatcherMap[$region->name] ?? $this->dispatcher;
        $this->dispatcher->dispatchEvent(new TokenRegionReturnEvent($this->currentRegion));
    }

    public function forceTokenizationEnd(): void
    {
        $this->dispatcher->dispatchEvent(new TokenRegionEndedEvent($this->currentRegion));
        $this->forceTokenizationEnd = true;
    }

    public function retryLastTokenTokenization(): void
    {
        $this->retryLastTokenTokenization = true;
    }

    public function markTokenizationStarted(): void
    {
        $this->dispatcher->dispatchEvent(new TokenizationStartedEvent());
    }

    public function markTokenizationFinished(): void
    {
        $this->dispatcher->dispatchEvent(new TokenizationFinishedEvent($this->forceTokenizationEnd));
    }

    public function getChunkSize(): int
    {
        return $this->chunkSize;
    }

    public function getSafeMargin(): int
    {
        return $this->safeMargin;
    }

    public function getAddingRetryLimit(): int
    {
        return $this->lastTokenAddingRetryLimit;
    }

    public function getOutput(): TokenRegion
    {
        return $this->output;
    }

    public function getCurrentRegion(): TokenRegion
    {
        return $this->currentRegion;
    }

    public function getPatternLibrary(): PatternLibrary
    {
        return $this->patternLibrary;
    }

    public function isApplyBofEofActive(): bool
    {
        return $this->applyBofEof;
    }

    public function isForceTokenizationEndActive(): bool
    {
        return $this->forceTokenizationEnd;
    }

    public function registerEventListener(
        TokenizationEventListener $listener,
        string $eventClassName,
        ?string $onlyOnRule = null,
        ?string $inRegion = null,
    ): void {
        if ($inRegion !== null) {
            if (!isset($this->regionToEventDispatcherMap[$inRegion])) {
                $this->regionToEventDispatcherMap[$inRegion] = new TokenizationEventDispatcher($this);
            }
            $this->regionToEventDispatcherMap[$inRegion]->registerEventListener($listener, $eventClassName, $onlyOnRule);
        } else {
            $this->dispatcher->registerEventListener($listener, $eventClassName, $onlyOnRule);
        }
    }

    public function registerPattern(
        Pattern $pattern,
        ?string $inRegion = null,
    ): void {
        if ($inRegion !== null) {
            if (!isset($this->regionToPatternLibraryMap[$inRegion])) {
                $this->regionToPatternLibraryMap[$inRegion] = new PatternLibrary([]);
            }
            $this->regionToPatternLibraryMap[$inRegion]->addPattern($pattern);
        } else {
            $this->patternLibrary->addPattern($pattern);
        }
    }
}
