<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\EventListener\Tokenization;

use LogicException;
use PhpArchitecture\Parser\Grammar\EventListener\RuleMatchedEventListener;
use PhpArchitecture\Parser\Grammar\Rule;
use PhpArchitecture\Parser\Tokenization\Event\Contract\TokenizationEvent;
use PhpArchitecture\Parser\Tokenization\Event\Contract\TokenizationEventListener;
use PhpArchitecture\Parser\Tokenization\Event\TokenMatchedEvent;
use PhpArchitecture\Parser\Tokenization\Model\TokenRegion;
use PhpArchitecture\Parser\Tokenization\Tokenization;

final class EndRegionEventListener implements TokenizationEventListener, RuleMatchedEventListener
{
    public function __construct(
        public readonly Rule $rule
    ) {}

    public function handle(TokenizationEvent $event, Tokenization $context): void
    {
        if (!$event instanceof TokenMatchedEvent) {
            return;
        }

        $parentRegion = $context->currentRegion->getMeta(TokenRegion::KEY_PARENT);
        if ($parentRegion === null) {
            throw new LogicException("It's forbidden to use rule for region closing without setup the parent region.");
        }

        $context->escapeToRegion($parentRegion);
    }

    public function priority(): int
    {
        return 0;
    }

    public function rule(): ?string
    {
        return $this->rule->name;
    }
}
