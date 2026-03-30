<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Definition\EventListener\Tokenization;

use PhpArchitecture\Parser\Grammar\Definition\EventListener\RuleMatchedEventListener;
use PhpArchitecture\Parser\Processing\Context\TokenizationContext;
use PhpArchitecture\Parser\Processing\Event\Tokenization\Contract\RemovableEventListener;
use PhpArchitecture\Parser\Processing\Event\Tokenization\Contract\TokenizationEvent;
use PhpArchitecture\Parser\Processing\Event\Tokenization\Contract\TokenizationEventListener;
use PhpArchitecture\Parser\Processing\Event\Tokenization\TokenAddedEvent;

final class NextTokenRemovalEventListener implements TokenizationEventListener, RemovableEventListener, RuleMatchedEventListener
{
    private bool $result = false;

    public function __construct(
        public readonly string $tokenName,
    ) {}

    public function handle(TokenizationEvent $event, TokenizationContext $context): void
    {
        if (!$event instanceof TokenAddedEvent) {
            return;
        }

        if ($event->name() !== $this->tokenName) {
            return;
        }

        $this->result = $context->removeLastToken($event->token);
    }

    public function priority(): int
    {
        return -100;
    }

    public function rule(): ?string
    {
        return $this->tokenName;
    }

    public function shouldBeRemoved(): bool
    {
        return $this->result;
    }
}
