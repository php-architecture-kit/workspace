<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\EventListener\Tokenization;

use PhpArchitecture\Parser\Grammar\Region;
use PhpArchitecture\Parser\Tokenization\Event\Contract\TokenizationEvent;
use PhpArchitecture\Parser\Tokenization\Event\Contract\TokenizationEventListener;
use PhpArchitecture\Parser\Tokenization\Event\TokenRegionEndedEvent;
use PhpArchitecture\Parser\Tokenization\Lexer;
use PhpArchitecture\Parser\Tokenization\Model\Position;
use PhpArchitecture\Parser\Tokenization\Model\StringStream;
use PhpArchitecture\Parser\Tokenization\Tokenization;
use PhpArchitecture\Parser\Tokenization\TokenizationContextCompiler;

final class RetokenizeRegionEventListener implements TokenizationEventListener
{
    public function __construct(
        public readonly Region $region,
    ) {}

    public function handle(TokenizationEvent $event, Tokenization $context): void
    {
        if (!$event instanceof TokenRegionEndedEvent || $this->region->name !== $event->name()) {
            return;
        }

        $tokenRegion = $event->region;
        $isPositioningActive = $tokenRegion->hasMeta(Position::KEY_START);

        $compiler = new TokenizationContextCompiler();
        $context = $compiler->compile(
            $this->region->config->insideGrammar,
            $isPositioningActive,
        );

        if ($isPositioningActive) {
            $startPosition = $tokenRegion->getMeta(Position::KEY_START);
            $context->currentRow = $startPosition->row;
            $context->currentColumn = $startPosition->column;
        }

        $lexer = new Lexer($context);
        $stream = new StringStream($tokenRegion->__toString());

        $retokenizedRegion = $lexer->process($stream);

        $tokenRegion->replaceTokenStream($retokenizedRegion->stream);
    }

    public function priority(): int
    {
        return 0;
    }
}
