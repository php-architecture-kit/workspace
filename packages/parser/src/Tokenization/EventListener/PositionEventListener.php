<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tokenization\EventListener;

use PhpArchitecture\Parser\Tokenization\Event\Contract\TokenizationEvent;
use PhpArchitecture\Parser\Tokenization\Event\Contract\TokenizationEventListener;
use PhpArchitecture\Parser\Tokenization\Event\TokenMatchedEvent;
use PhpArchitecture\Parser\Tokenization\Event\TokenRegionEndedEvent;
use PhpArchitecture\Parser\Tokenization\Model\Position;
use PhpArchitecture\Parser\Tokenization\Model\Token;
use PhpArchitecture\Parser\Tokenization\Model\TokenRegion;
use PhpArchitecture\Parser\Tokenization\Tokenization;

final class PositionEventListener implements TokenizationEventListener
{
    public function handle(TokenizationEvent $event, Tokenization $context): void
    {
        if (!$event instanceof TokenMatchedEvent && !$event instanceof TokenRegionEndedEvent) {
            return;
        }

        if ($event instanceof TokenMatchedEvent) {
            $token = $event->token;
            $this->handleToken($token, $context);
        }

        if ($event instanceof TokenRegionEndedEvent) {
            $region = $event->region;
            $this->handleTokenRegion($region);
        }
    }

    public function priority(): int
    {
        return 0;
    }

    private function handleToken(Token $token, Tokenization $context): void
    {
        $value = $token->raw;

        $newlines = substr_count($value, "\n");
        $valueLength = strlen($value);

        $token->setMeta(
            Position::KEY_START,
            new Position(
                $context->currentRow,
                $context->currentColumn
            )
        );

        if ($newlines > 0) {
            $lastNewlinePos = strrpos($value, "\n") ?: 0;
            $lastColumn = $context->currentColumn;
            $context->currentRow += $newlines;
            $context->currentColumn = $valueLength - $lastNewlinePos;

            $endsWithNewline = str_ends_with($value, "\n");
            if ($endsWithNewline) {
                $lastNewlinePos = strrpos(substr($value, 0, -1), "\n");
                if ($lastNewlinePos === false) {
                    $token->setMeta(
                        Position::KEY_END,
                        new Position(
                            $context->currentRow - 1,
                            $lastColumn + $valueLength - 1
                        )
                    );
                }
            }

            $lastPartLength = $valueLength - $lastNewlinePos - 1;
            $token->setMeta(
                Position::KEY_END,
                new Position(
                    $endsWithNewline ? $context->currentRow - 1 : $context->currentRow,
                    $lastPartLength > 0 ? $lastPartLength : 1
                )
            );
        } else {
            $token->setMeta(
                Position::KEY_END,
                new Position(
                    $context->currentRow,
                    $context->currentColumn + $valueLength
                )
            );
            $context->currentColumn += $valueLength;
        }
    }

    private function handleTokenRegion(TokenRegion $region): void
    {
        $firstToken = $region->firstToken();
        $lastToken = $region->lastToken();

        if ($firstToken !== null) {
            $region->setMeta(
                Position::KEY_START,
                clone $firstToken->getMeta(Position::KEY_START)
            );
        }

        if ($lastToken !== null) {
            $region->setMeta(
                Position::KEY_END,
                clone $lastToken->getMeta(Position::KEY_END)
            );
        }
    }
}
