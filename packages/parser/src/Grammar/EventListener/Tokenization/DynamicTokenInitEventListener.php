<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\EventListener\Tokenization;

use PhpArchitecture\Parser\Grammar\Definition\Regex\CallbackRule;
use PhpArchitecture\Parser\Grammar\EventListener\RuleMatchedEventListener;
use PhpArchitecture\Parser\Grammar\Region;
use PhpArchitecture\Parser\Grammar\Rule;
use PhpArchitecture\Parser\Tokenization\Event\Contract\TokenizationEvent;
use PhpArchitecture\Parser\Tokenization\Event\Contract\TokenizationEventListener;
use PhpArchitecture\Parser\Tokenization\Event\TokenAddedEvent;
use PhpArchitecture\Parser\Tokenization\Tokenization;
use PhpArchitecture\Parser\Tokenization\TokenizationContextCompiler;

final class DynamicTokenInitEventListener implements TokenizationEventListener, RuleMatchedEventListener
{
    public function __construct(
        public readonly string $triggerRule,
        public readonly Rule $dynamicRule,
        public readonly Region $targetRegion
    ) {
        if (!$dynamicRule->definition instanceof CallbackRule) {
            throw new \InvalidArgumentException('Dynamic rule must have a callback definition');
        }
    }

    public function handle(TokenizationEvent $event, Tokenization $context): void
    {
        if (!$event instanceof TokenAddedEvent) {
            return;
        }

        $token = $event->token;
        /** @var CallbackRule $callbackDefinition */
        $callbackDefinition = $this->dynamicRule->definition;
        $newRule = $callbackDefinition->toTokenRule($this->dynamicRule, $token);

        $newPattern = (new TokenizationContextCompiler)->mapRuleToPattern($newRule);
        $context->regionToPatternLibraryMap[$this->targetRegion->name]->addPattern($newPattern);
    }

    public function priority(): int
    {
        return 0;
    }

    public function rule(): ?string
    {
        return $this->triggerRule;
    }
}
