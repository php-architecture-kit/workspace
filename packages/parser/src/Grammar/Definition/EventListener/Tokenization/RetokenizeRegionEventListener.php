<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Definition\EventListener\Tokenization;

use PhpArchitecture\Parser\Grammar\Compiled\Model\CompiledGrammar;
use PhpArchitecture\Parser\Processing\Event\Tokenization\Contract\TokenizationEvent;
use PhpArchitecture\Parser\Processing\Event\Tokenization\Contract\TokenizationEventListener;
use PhpArchitecture\Parser\Processing\Event\Tokenization\TokenRegionEndedEvent;
use PhpArchitecture\Parser\Tokenization\Lexer;
use PhpArchitecture\Parser\Processing\Model\Tokenization\Position;
use PhpArchitecture\Parser\Tokenization\Model\StringStream;
use PhpArchitecture\Parser\Processing\Context\TokenizationContext;
use PhpArchitecture\Parser\Tokenization\Context\TokenizationContextCompiler;

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
