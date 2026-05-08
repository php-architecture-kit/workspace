<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Grammar\Definition\EventListener\Tokenization;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\EventListener\RuleMatchedEventListener;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Region;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\Contract\TokenizationEvent;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\Contract\TokenizationEventListener;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\TokenAddedEvent;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\TokenMatchedEvent;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\TokenRegion;
use PhpArchitecture\Parser\Foundation\Tokenization\Contract\TokenizationContext;

final class StartRegionEventListener implements TokenizationEventListener, RuleMatchedEventListener
{
    public const KEY_STARTED_REGION = 'startedRegion';
    public const KEY_STARTED_BY = 'startedBy';
    public const KEY_CAUSED_BY_EVENT = 'startCausedByEvent';

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
        $newRegion = TokenRegion::new($this->region->name)
            ->addTag($this->region->config->nodeType->value);

        $newRegion->setMeta(TokenRegion::KEY_PARENT, $context->getCurrentRegion());

        $token->setMeta(self::KEY_STARTED_REGION, $newRegion);
        $newRegion->setMeta(self::KEY_STARTED_BY, $token);
        $newRegion->setMeta(self::KEY_CAUSED_BY_EVENT, $event);
        $newRegion->addTag(...$this->region->tags);

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
