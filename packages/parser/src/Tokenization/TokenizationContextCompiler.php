<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tokenization;

use PhpArchitecture\Parser\Grammar\Definition\Regex\RegexRule;
use PhpArchitecture\Parser\Grammar\Grammar;
use PhpArchitecture\Parser\Grammar\Rule;
use PhpArchitecture\Parser\Tokenization\Event\Contract\TokenizationEventListener;
use PhpArchitecture\Parser\Tokenization\Event\TokenMatchedEvent;
use PhpArchitecture\Parser\Tokenization\Event\TokenRegionEndedEvent;
use PhpArchitecture\Parser\Tokenization\EventListener\PositionEventListener;
use PhpArchitecture\Parser\Tokenization\Model\Pattern;
use PhpArchitecture\Parser\Tokenization\Model\PatternLibrary;

class TokenizationContextCompiler
{
    public function compile(
        Grammar $grammar,
        int $chunkSize = 8192,
        int $safeMargin = 1024,
        bool $applyRowColTracking = true,
    ): Tokenization {
        $grammar->compile();

        $rootName = $grammar->rootRegion->name;
        $context = new Tokenization($rootName, $grammar->requireBofEof, $chunkSize, $safeMargin);

        $this->setupPatternLibraries($context, $grammar);
        $this->setupEventDispatchers($context, $grammar);

        if ($applyRowColTracking) {
            $this->applyRowColTracking($context);
        }

        return $context;
    }

    public function mapRuleToPattern(Rule $rule): Pattern
    {
        if (!$rule->definition instanceof RegexRule) {
            throw new \InvalidArgumentException('Rule definition must be a RegexRule');
        }

        return new Pattern($rule->name, $rule->definition->regex, $rule->priority);
    }

    private function setupPatternLibraries(Tokenization $context, Grammar $grammar): void
    {
        $allRegions = $grammar->getAllRegions();
        foreach ($allRegions as $region) {
            $patterns = [];
            foreach ($region->rules as $rule) {
                $patterns[] = $this->mapRuleToPattern($rule);
            }
            $context->regionToPatternLibraryMap[$region->name] = new PatternLibrary($patterns);
        }

        $context->patternLibrary = $context->regionToPatternLibraryMap[$grammar->rootRegion->name];
    }

    private function setupEventDispatchers(Tokenization $context, Grammar $grammar): void
    {
        $allRegions = $grammar->getAllRegions();
        foreach ($allRegions as $region) {
            $eventDispatcher = new TokenizationEventDispatcher($context);
            foreach ($region->eventSubscribers as $eventSubscriber) {
                if ($eventSubscriber->listener instanceof TokenizationEventListener) {
                    $eventDispatcher->registerEventListener(
                        $eventSubscriber->listener,
                        $eventSubscriber->eventClassName,
                        $eventSubscriber->onlyForRuleName
                    );
                }
            }
            $context->regionToEventDispatcherMap[$region->name] = $eventDispatcher;
        }

        $context->dispatcher = $context->regionToEventDispatcherMap[$grammar->rootRegion->name];
    }

    private function applyRowColTracking(Tokenization $context): void
    {
        foreach ($context->regionToEventDispatcherMap as $eventDispatcher) {
            $eventDispatcher->registerEventListener(
                new PositionEventListener(),
                TokenMatchedEvent::class
            );
            $eventDispatcher->registerEventListener(
                new PositionEventListener(),
                TokenRegionEndedEvent::class
            );
        }
    }
}
