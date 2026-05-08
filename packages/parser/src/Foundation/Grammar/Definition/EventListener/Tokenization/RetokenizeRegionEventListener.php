<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Grammar\Definition\EventListener\Tokenization;

use PhpArchitecture\Parser\Foundation\Grammar\Compiled\Model\CompiledGrammar;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\Contract\TokenizationEvent;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\Contract\TokenizationEventListener;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\TokenRegionEndedEvent;
use PhpArchitecture\Parser\Foundation\Tokenization\Lexer;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\Position;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\StringStream;
use PhpArchitecture\Parser\Foundation\Tokenization\Contract\TokenizationContext;
use PhpArchitecture\Parser\Foundation\Tokenization\Context\TokenizationContextCompiler;

final class RetokenizeRegionEventListener implements TokenizationEventListener
{
    public function __construct(
        private readonly string $regionName,
        private readonly CompiledGrammar $compiledGrammar,
    ) {}

    public function handle(TokenizationEvent $event, TokenizationContext $context): void
    {
        if (!$event instanceof TokenRegionEndedEvent || $this->regionName !== $event->name()) {
            return;
        }

        $tokenRegion = $event->region;
        $isPositioningActive = $tokenRegion->hasMeta(Position::KEY_START);

        $compiler = new TokenizationContextCompiler();
        $context = $compiler->compile(
            $this->compiledGrammar,
            applyRowColTracking: $isPositioningActive,
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
