<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Definition\EventListener\Tokenization;

use PhpArchitecture\Parser\Grammar\Definition\EventListener\RuleMatchedEventListener;
use PhpArchitecture\Parser\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Grammar\Definition\Region;
use PhpArchitecture\Parser\Processing\Event\Tokenization\Contract\TokenizationEvent;
use PhpArchitecture\Parser\Processing\Event\Tokenization\Contract\TokenizationEventListener;
use PhpArchitecture\Parser\Processing\Event\Tokenization\TokenAddedEvent;
use PhpArchitecture\Parser\Processing\Event\Tokenization\TokenMatchedEvent;
use PhpArchitecture\Parser\Processing\Model\Tokenization\TokenRegion;
use PhpArchitecture\Parser\Processing\Context\TokenizationContext;

final class StartRegionEventListener implements TokenizationEventListener, RuleMatchedEventListener
{
    public const KEY_STARTED_REGION = 'startedRegion';
    public const KEY_STARTED_BY = 'startedBy';

    public function __construct(
        public readonly Region $region,
        public readonly Rule $rule,
    ) {}

    public function handle(TokenizationEvent $event, TokenizationContext $context): void
    {
        if (!$event instanceof TokenMatchedEvent && !$event instanceof TokenAddedEvent) {
            return;
        }

        $token = $event->token;
        $newRegion = TokenRegion::new($this->region->name);
        $newRegion->setMeta(TokenRegion::KEY_PARENT, $context->getCurrentRegion());

        $token->setMeta(self::KEY_STARTED_REGION, $newRegion);
        $newRegion->setMeta(self::KEY_STARTED_BY, $token);

        $context->addRegion($newRegion);
    }

    public function priority(): int
    {
        return -9999;
    }

    public function rule(): ?string
    {
        return $this->rule->name;
    }
}
