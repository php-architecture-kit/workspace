<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tokenization\EventListener;

use PhpArchitecture\Parser\Tokenization\Event\Contract\TokenizationEvent;
use PhpArchitecture\Parser\Tokenization\Event\Contract\TokenizationEventListener;
use PhpArchitecture\Parser\Tokenization\Event\TokenMatchedEvent;
use PhpArchitecture\Parser\Tokenization\Model\Position;
use PhpArchitecture\Parser\Tokenization\Tokenization;

final class PositionEventListener implements TokenizationEventListener
{
    public function handle(TokenizationEvent $event, Tokenization $context): void
    {
        if (!$event instanceof TokenMatchedEvent) {
            return;
        }

        $token = $event->token;
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
        }
    }

    public function priority(): int
    {
        return 0;
    }
}
