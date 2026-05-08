<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Grammar\Definition\EventListener\Tokenization;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\EventListener\RuleMatchedEventListener;
use PhpArchitecture\Parser\Foundation\Tokenization\Contract\TokenizationContext;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\Contract\RemovableEventListener;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\Contract\TokenizationEvent;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\Contract\TokenizationEventListener;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\TokenAddedEvent;

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
