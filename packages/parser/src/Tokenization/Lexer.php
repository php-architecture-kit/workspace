<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tokenization;

use PhpArchitecture\Parser\Processing\Context\TokenizationContext;
use PhpArchitecture\Parser\Tokenization\Model\StringStream;
use PhpArchitecture\Parser\Processing\Model\Tokenization\Token;
use PhpArchitecture\Parser\Processing\Model\Tokenization\TokenRegion;

final class Lexer
{
    private int $addingRetryCount = 0;

    public function __construct(
        private readonly TokenizationContext $context
    ) {}

    public function process(StringStream $stream): TokenRegion
    {
        $this->context->markTokenizationStarted();

        if ($this->context->isApplyBofEofActive()) {
            $this->context->addToken(Token::bof());
        }

        $this->tokenizeContent($stream);

        if ($this->context->isApplyBofEofActive() && $stream->isEof()) {
            $this->context->addToken(Token::eof($stream->position()));
        }

        $this->context->markTokenizationFinished();

        return $this->context->getOutput();
    }

    private function tokenizeContent(StringStream $stream): void
    {
        while (!$stream->isEof() && !$this->context->isForceTokenizationEndActive()) {
            $content = $stream->getChunk($this->context->getChunkSize());
            $contentSize = strlen($content);
            $pos = 0;
            $endPos = $contentSize - (
                $contentSize === $this->context->getChunkSize()
                ? $this->context->getSafeMargin()
                : 0
            );

            do {
                $currentPos = $stream->position();
                $bestMatch = $this->findBestMatch($content, $pos);

                if ($bestMatch !== null) {
                    $tokenName = $bestMatch['name'];
                    $value = $bestMatch['value'];
                    $length = strlen($value);

                    $added = $this->context->addToken(
                        Token::default(
                            name: $tokenName,
                            raw: $value,
                            startPosition: $currentPos + $pos,
                            endPosition: $currentPos + $pos + $length,
                        )->replaceTags($bestMatch['tags']),
                    );

                    if (!$added) {
                        while ($this->addingRetryCount < $this->context->getAddingRetryLimit()) {
                            $this->addingRetryCount++;
                            continue 2;
                        }
                    }

                    $this->addingRetryCount = 0;
                    $stream->advance($length);
                    $pos += $length;

                    continue;
                }

                $char = mb_substr($content, $pos, 1);
                $charLen = strlen($char);

                if ($charLen === 0) {
                    break;
                }

                $added = $this->context->addToken(
                    Token::unknown(character: $char, position: $currentPos),
                );

                if (!$added) {
                    while ($this->addingRetryCount < $this->context->getAddingRetryLimit()) {
                        $this->addingRetryCount++;
                        continue 2;
                    }
                }

                $this->addingRetryCount = 0;
                $stream->advance($charLen);
                $pos += $charLen;
            } while ($pos < $endPos);
        }
    }

    /**
     * @return array{name:string,value:string,tags:string[]}|null
     */
    private function findBestMatch(string $content, int $pos): ?array
    {
        $bestMatch = null;
        $bestLength = 0;
        $bestPriority = PHP_INT_MIN;
        $bestIndex = PHP_INT_MAX;

        foreach ($this->context->getPatternLibrary()->patterns as $tokenName => $pattern) {
            $priority = $pattern->priority;

            if (preg_match($pattern->pattern, $content, $matches, 0, $pos) === 1) {
                $matchLength = strlen($matches[0]);

                if ($matchLength === 0) {
                    continue;
                }

                $index = $this->context->getPatternLibrary()->patternIndexMap[$tokenName];
                if (
                    $priority > $bestPriority ||
                    ($priority === $bestPriority && $matchLength > $bestLength) ||
                    ($priority === $bestPriority && $matchLength === $bestLength && $index < $bestIndex)
                ) {
                    $bestMatch = ['name' => $tokenName, 'value' => $matches[0], 'tags' => $pattern->tags];
                    $bestLength = $matchLength;
                    $bestPriority = $priority;
                    $bestIndex = $index;
                }
            }
        }

        if ($bestMatch === null) {
            return null;
        }

        return $bestMatch;
    }
}
