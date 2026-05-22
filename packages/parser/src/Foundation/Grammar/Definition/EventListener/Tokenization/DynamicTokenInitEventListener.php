<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Grammar\Definition\EventListener\Tokenization;

use InvalidArgumentException;
use PhpArchitecture\Parser\Foundation\Grammar\Compiled\Compiler\RuleToPatternCompiler;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Regex\CallbackRule;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\EventListener\RuleMatchedEventListener;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Region;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Foundation\Tokenization\Contract\TokenizationContext;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\Contract\TokenizationEvent;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\Contract\TokenizationEventListener;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\TokenAddedEvent;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\Pattern;

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
        assert($newPattern instanceof Pattern);
        $context->registerPattern($newPattern, $this->targetRegion->name);
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
