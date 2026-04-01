<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tokenization\Context;

use PhpArchitecture\Parser\Grammar\Compiled\Model\CompiledGrammar;
use PhpArchitecture\Parser\Processing\Context\TokenizationContext;
use PhpArchitecture\Parser\Processing\Event\Tokenization\TokenMatchedEvent;
use PhpArchitecture\Parser\Processing\Event\Tokenization\TokenRegionEndedEvent;
use PhpArchitecture\Parser\Processing\Extension\Tokenization\IdentifyRowsAndColumns;

final class TokenizationContextCompiler
{
    public function compile(
        CompiledGrammar $grammar,
        bool $applyRowColTracking = true,
    ): TokenizationContext {
        $patternLibraries = [];
        $eventDispatchers = [];

        foreach ($grammar->regions as $regionName => $region) {
            $patternLibraries[$regionName] = $region->patternLibrary;
        }

        $context = new DefaultTokenizationContext(
            rootName: $grammar->rootRegionName,
            applyBofEof: $grammar->requireBofEof,
            regionToPatternLibraryMap: $patternLibraries,
            regionToEventDispatcherMap: [],
        );

        $applyRowColTrackingInstance = new IdentifyRowsAndColumns();
        foreach ($grammar->regions as $regionName => $region) {
            $dispatcher = new TokenizationEventDispatcher($context);

            foreach ($region->eventSubscribers as $subscriber) {
                $dispatcher->registerEventListener(
                    $subscriber->listener,
                    $subscriber->eventClassName,
                    $subscriber->onlyForRuleName
                );
            }

            if ($applyRowColTracking) {
                $dispatcher->registerEventListener($applyRowColTrackingInstance, TokenMatchedEvent::class);
                $dispatcher->registerEventListener($applyRowColTrackingInstance, TokenRegionEndedEvent::class);
            }

            $eventDispatchers[$regionName] = $dispatcher;
        }

        $reflection = new \ReflectionProperty($context, 'regionToEventDispatcherMap');
        $reflection->setValue($context, $eventDispatchers);

        $dispatcherReflection = new \ReflectionProperty($context, 'dispatcher');
        $rootDispatcher = $eventDispatchers[$grammar->rootRegionName] ?? reset($eventDispatchers);
        $dispatcherReflection->setValue($context, $rootDispatcher);

        return $context;
    }
}
