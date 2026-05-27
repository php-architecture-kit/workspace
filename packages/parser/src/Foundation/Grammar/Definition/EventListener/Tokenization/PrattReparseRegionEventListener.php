<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Grammar\Definition\EventListener\Tokenization;

use PhpArchitecture\Parser\Foundation\Tokenization\Contract\TokenizationContext;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\Contract\TokenizationEvent;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\Contract\TokenizationEventListener;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\TokenizationFinishedEvent;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\TokenRegionEndedEvent;
use PhpArchitecture\Parser\Foundation\Tokenization\Pratt\PrattGrammarDefinition;
use PhpArchitecture\Parser\Foundation\Tokenization\Pratt\PrattParser;

final class PrattReparseRegionEventListener implements TokenizationEventListener
{
    public function __construct(
        private readonly string $regionName,
        private readonly string $groupedRegionName,
        private readonly PrattGrammarDefinition $definition,
    ) {}

    public function handle(TokenizationEvent $event, TokenizationContext $context): void
    {
        if ($event instanceof TokenRegionEndedEvent) {
            if ($this->regionName !== $event->name()) {
                return;
            }
            $region = $event->region;
        } elseif ($event instanceof TokenizationFinishedEvent) {
            $region = $context->getOutput();
            if ($region->name !== $this->regionName) {
                return;
            }
        } else {
            return;
        }

        $parser = new PrattParser();
        $reparsed = $parser->parse($region->stream, $this->definition, $this->groupedRegionName);

        $region->replaceTokenStream($reparsed);
    }

    public function priority(): int
    {
        return 0;
    }
}
