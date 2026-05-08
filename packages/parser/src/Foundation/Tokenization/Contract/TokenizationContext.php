<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Tokenization\Contract;

use PhpArchitecture\Parser\Foundation\Tokenization\Event\Contract\TokenizationEventListener;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\Pattern;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\PatternLibrary;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\Token;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\TokenRegion;
use PhpArchitecture\Parser\Foundation\Shared\Meta\MetaInterface;

interface TokenizationContext extends MetaInterface
{
    public function addToken(Token $token): bool;
    public function removeLastToken(Token $token): bool;
    public function addRegion(TokenRegion $tokenRegion): bool;
    public function removeLastRegion(TokenRegion $tokenRegion): bool;

    public function escapeToRegion(TokenRegion $region): void;
    public function forceTokenizationEnd(): void;
    public function retryLastTokenTokenization(): void;

    public function getChunkSize(): int;
    public function getSafeMargin(): int;
    public function getAddingRetryLimit(): int;
    public function getOutput(): TokenRegion;
    public function getCurrentRegion(): TokenRegion;
    public function getPatternLibrary(): PatternLibrary;

    public function isApplyBofEofActive(): bool;
    public function isForceTokenizationEndActive(): bool;

    public function markTokenizationStarted(): void;
    public function markTokenizationFinished(): void;

    /** @param class-string<TokenizationEvent> $eventClassName */
    public function registerEventListener(
        TokenizationEventListener $listener,
        string $eventClassName,
        ?string $onlyOnRule = null,
        ?string $inRegion = null,
    ): void;

    public function registerPattern(
        Pattern $pattern,
        ?string $inRegion = null,
    ): void;
}
