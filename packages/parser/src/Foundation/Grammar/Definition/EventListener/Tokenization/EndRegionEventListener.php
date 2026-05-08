<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Grammar\Definition\EventListener\Tokenization;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\EventListener\RuleMatchedEventListener;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\Contract\TokenizationEvent;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\Contract\TokenizationEventListener;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\TokenAddedEvent;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\TokenMatchedEvent;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\TokenRegion;
use PhpArchitecture\Parser\Foundation\Tokenization\Contract\TokenizationContext;

final class EndRegionEventListener implements TokenizationEventListener, RuleMatchedEventListener
{
    public const KEY_CAUSED_BY_EVENT = 'endCausedByEvent';

    public function __construct(
        public readonly Rule $rule,
        public readonly bool $negated = false,
        public readonly bool $allowedForTokenWhichStartedRegion = false,
        public readonly bool $callLastTokenRemoval = true,
    ) {}

    public function handle(TokenizationEvent $event, TokenizationContext $context): void
    {
        if (!$event instanceof TokenMatchedEvent && !$event instanceof TokenAddedEvent) {
            return;
        }

        if ($this->negated) {
            if (in_array($this->rule->name, array_merge([$event->token->name], $event->token->tags))) {
                return;
            }
        }

        $currentRegion = $context->getCurrentRegion();
        $parentRegion = $currentRegion->getMeta(TokenRegion::KEY_PARENT);
        $token = $event->token;
        if (
            !$this->allowedForTokenWhichStartedRegion &&
            $token->hasMeta(StartRegionEventListener::KEY_STARTED_REGION) &&
            $token->getMeta(StartRegionEventListener::KEY_STARTED_REGION)->name === $currentRegion->name
        ) {
            return;
        }

        if ($this->callLastTokenRemoval && $event instanceof TokenAddedEvent) {
            $context->removeLastToken($event->token);
        }

        if ($parentRegion !== null) {
            $context->escapeToRegion($parentRegion);
        } else {
            $context->forceTokenizationEnd();
        }

        if ($this->callLastTokenRemoval && $event instanceof TokenMatchedEvent) {
            $context->registerEventListener(
                new NextTokenRemovalEventListener($token->name),
                TokenAddedEvent::class,
                $token->name,
            );
        }

        $currentRegion->setMeta(
            self::KEY_CAUSED_BY_EVENT,
            $event,
        );
    }

    public function priority(): int
    {
        return 0;
    }

    public function rule(): ?string
    {
        return $this->negated ? null : $this->rule->name;
    }
}
