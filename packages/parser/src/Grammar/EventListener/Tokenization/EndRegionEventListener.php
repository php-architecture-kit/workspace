<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\EventListener\Tokenization;

use PhpArchitecture\Parser\Grammar\EventListener\RuleMatchedEventListener;
use PhpArchitecture\Parser\Grammar\Rule;
use PhpArchitecture\Parser\Tokenization\Event\Contract\TokenizationEvent;
use PhpArchitecture\Parser\Tokenization\Event\Contract\TokenizationEventListener;
use PhpArchitecture\Parser\Tokenization\Event\TokenAddedEvent;
use PhpArchitecture\Parser\Tokenization\Event\TokenMatchedEvent;
use PhpArchitecture\Parser\Tokenization\Model\TokenRegion;
use PhpArchitecture\Parser\Tokenization\Tokenization;

final class EndRegionEventListener implements TokenizationEventListener, RuleMatchedEventListener
{
    public function __construct(
        public readonly Rule $rule,
        public readonly bool $negated = false
    ) {}

    public function handle(TokenizationEvent $event, Tokenization $context): void
    {
        if (!$event instanceof TokenMatchedEvent && !$event instanceof TokenAddedEvent) {
            return;
        }

        if ($this->negated && in_array($event->token->name, array_merge([$this->rule->name], $this->rule->tags))) {
            return;
        }

        $parentRegion = $context->currentRegion->getMeta(TokenRegion::KEY_PARENT);

        if ($parentRegion !== null) {
            $context->escapeToRegion($parentRegion);
        } else {
            $context->forceEscape();
        }
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
