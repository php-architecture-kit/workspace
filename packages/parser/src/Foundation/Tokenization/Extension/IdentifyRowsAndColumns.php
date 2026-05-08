<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Tokenization\Extension;

use PhpArchitecture\Parser\Foundation\Tokenization\Contract\TokenizationContext;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\Contract\TokenizationEvent;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\Contract\TokenizationEventListener;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\TokenMatchedEvent;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\TokenRegionEndedEvent;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\Position;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\Token;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\TokenRegion;

final class IdentifyRowsAndColumns implements TokenizationEventListener
{
    private const CURRENT_ROW = 'currentRow';
    private const CURRENT_COLUMN = 'currentColumn';

    public function handle(TokenizationEvent $event, TokenizationContext $context): void
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
        return 9999;
    }

    private function handleToken(Token $token, TokenizationContext $context): void
    {
        $value = $token->raw;

        $newlines = substr_count($value, "\n");
        $valueLength = strlen($value);

        $token->setMeta(
            Position::KEY_START,
            new Position(
                $context->getMeta(self::CURRENT_ROW),
                $context->getMeta(self::CURRENT_COLUMN),
            ),
        );

        if ($newlines > 0) {
            $lastNewlinePos = strrpos($value, "\n") ?: 0;
            $lastColumn = $context->getMeta(self::CURRENT_COLUMN);
            $context->setMeta(self::CURRENT_ROW, $context->getMeta(self::CURRENT_ROW) + $newlines);
            $context->setMeta(self::CURRENT_COLUMN, $valueLength - $lastNewlinePos);

            $endsWithNewline = str_ends_with($value, "\n");
            if ($endsWithNewline) {
                $lastNewlinePos = strrpos(substr($value, 0, -1), "\n");
                if ($lastNewlinePos === false) {
                    $token->setMeta(
                        Position::KEY_END,
                        new Position(
                            $context->getMeta(self::CURRENT_ROW) - 1,
                            $lastColumn + $valueLength - 1,
                        ),
                    );
                }
            }

            $lastPartLength = $valueLength - $lastNewlinePos - 1;
            $token->setMeta(
                Position::KEY_END,
                new Position(
                    $endsWithNewline ? $context->getMeta(self::CURRENT_ROW) - 1 : $context->getMeta(self::CURRENT_ROW),
                    $lastPartLength > 0 ? $lastPartLength : 1,
                ),
            );
        } else {
            $token->setMeta(
                Position::KEY_END,
                new Position(
                    $context->getMeta(self::CURRENT_ROW),
                    $context->getMeta(self::CURRENT_COLUMN) + $valueLength,
                ),
            );
            $context->setMeta(self::CURRENT_COLUMN, $context->getMeta(self::CURRENT_COLUMN) + $valueLength);
        }
    }

    private function handleTokenRegion(TokenRegion $region): void
    {
        $firstToken = $region->firstToken();
        $lastToken = $region->lastToken();

        if ($firstToken !== null) {
            $region->setMeta(
                Position::KEY_START,
                clone $firstToken->getMeta(Position::KEY_START),
            );
        }

        if ($lastToken !== null) {
            $region->setMeta(
                Position::KEY_END,
                clone $lastToken->getMeta(Position::KEY_END),
            );
        }
    }
}
