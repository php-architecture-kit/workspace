<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tokenization;

use PhpArchitecture\Parser\Tokenization\Model\StringStream;
use PhpArchitecture\Parser\Tokenization\Model\Token;
use PhpArchitecture\Parser\Tokenization\Model\TokenRegion;

final class Lexer
{
    public function __construct(
        private readonly Tokenization $context
    ) {}

    public function process(StringStream $stream): TokenRegion
    {
        $this->context->markTokenizationStarted();

        if ($this->context->applyBofEof) {
            $this->context->addToken(Token::bof());
        }

        $this->tokenizeContent($stream);

        if ($this->context->applyBofEof && $stream->isEof()) {
            $this->context->addToken(Token::eof($stream->position()));
        }

        $this->context->markTokenizationFinished();

        return $this->context->output;
    }

    private function tokenizeContent(StringStream $stream): void
    {
        while (!$stream->isEof() && !$this->context->forceTokenizationEnd) {
            $content = $stream->getChunk($this->context->chunkSize);
            $contentSize = strlen($content);
            $pos = 0;
            $endPos = $contentSize - (
                $contentSize === $this->context->chunkSize
                ? $this->context->safeMargin
                : 0
            );

            do {
                $currentPos = $stream->position();
                $bestMatch = $this->findBestMatch($content, $pos);

                if ($bestMatch !== null) {
                    $tokenName = $bestMatch['name'];
                    $value = $bestMatch['value'];
                    $length = strlen($value);

                    $this->context->addToken(
                        Token::default(
                            name: $tokenName,
                            raw: $value,
                            startPosition: $currentPos + $pos,
                            endPosition: $currentPos + $pos + $length
                        )
                    );

                    $stream->advance($length);
                    $pos += $length;

                    continue;
                }

                $char = mb_substr($content, $pos, 1);
                $charLen = strlen($char);

                if ($charLen === 0) {
                    break;
                }

                $this->context->addToken(
                    Token::unknown(character: $char, position: $currentPos)
                );
                $stream->advance($charLen);
                $pos += $charLen;
            } while ($pos < $endPos);
        }
    }

    /**
     * @return array{name:string,value:string}|null
     */
    private function findBestMatch(string $content, int $pos): ?array
    {
        $bestMatch = null;
        $bestLength = 0;
        $bestPriority = PHP_INT_MIN;
        $bestIndex = PHP_INT_MAX;

        foreach ($this->context->patternLibrary->patterns as $tokenName => $pattern) {
            $priority = $pattern->priority;

            if (preg_match($pattern->pattern, $content, $matches, 0, $pos) === 1) {
                $matchLength = strlen($matches[0]);

                if ($matchLength === 0) {
                    continue;
                }

                $index = $this->context->patternLibrary->patternIndexMap[$tokenName];
                if (
                    $priority > $bestPriority ||
                    ($priority === $bestPriority && $matchLength > $bestLength) ||
                    ($priority === $bestPriority && $matchLength === $bestLength && $index < $bestIndex)
                ) {
                    $bestMatch = ['name' => $tokenName, 'value' => $matches[0]];
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
