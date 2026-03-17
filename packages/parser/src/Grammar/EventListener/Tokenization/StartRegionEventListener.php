<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\EventListener\Tokenization;

use PhpArchitecture\Parser\Grammar\EventListener\RuleMatchedEventListener;
use PhpArchitecture\Parser\Grammar\Rule;
use PhpArchitecture\Parser\Grammar\Region;
use PhpArchitecture\Parser\Tokenization\Event\Contract\TokenizationEvent;
use PhpArchitecture\Parser\Tokenization\Event\Contract\TokenizationEventListener;
use PhpArchitecture\Parser\Tokenization\Event\TokenMatchedEvent;
use PhpArchitecture\Parser\Tokenization\Model\TokenRegion;
use PhpArchitecture\Parser\Tokenization\Tokenization;

final class StartRegionEventListener implements TokenizationEventListener, RuleMatchedEventListener
{
    public function __construct(
        public readonly Region $region,
        public readonly Rule $rule,
    ) {}

    public function handle(TokenizationEvent $event, Tokenization $context): void
    {
        if (!$event instanceof TokenMatchedEvent) {
            return;
        }

        $newRegion = TokenRegion::new($this->region->name);
        $newRegion->setMeta(TokenRegion::KEY_PARENT, $context->currentRegion);
        $context->addRegion($newRegion);
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
