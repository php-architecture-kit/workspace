<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Definition\EventListener\Tokenization;

use InvalidArgumentException;
use PhpArchitecture\Parser\Grammar\Compiled\Compiler\RuleToPatternCompiler;
use PhpArchitecture\Parser\Grammar\Definition\Model\Regex\CallbackRule;
use PhpArchitecture\Parser\Grammar\Definition\EventListener\RuleMatchedEventListener;
use PhpArchitecture\Parser\Grammar\Definition\Region;
use PhpArchitecture\Parser\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Processing\Context\TokenizationContext;
use PhpArchitecture\Parser\Processing\Event\Tokenization\Contract\TokenizationEvent;
use PhpArchitecture\Parser\Processing\Event\Tokenization\Contract\TokenizationEventListener;
use PhpArchitecture\Parser\Processing\Event\Tokenization\TokenAddedEvent;

final class DynamicTokenInitEventListener implements TokenizationEventListener, RuleMatchedEventListener
{
    public function __construct(
        public readonly string $triggerRule,
        public readonly Rule $dynamicRule,
        public readonly Region $targetRegion
    ) {
        if (!$dynamicRule->definition instanceof CallbackRule) {
            throw new InvalidArgumentException('Dynamic rule must have a callback definition');
        }
    }

    public function handle(TokenizationEvent $event, TokenizationContext $context): void
    {
        if (!$event instanceof TokenAddedEvent) {
            return;
        }

        $token = $event->token;
        /** @var CallbackRule $callbackDefinition */
        $callbackDefinition = $this->dynamicRule->definition;
        $newRule = $callbackDefinition->toTokenRule($this->dynamicRule, $token);

        $newPattern = (new RuleToPatternCompiler())->compileRule($newRule);
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
