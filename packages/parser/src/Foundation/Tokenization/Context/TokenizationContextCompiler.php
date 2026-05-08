<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Tokenization\Context;

use PhpArchitecture\Parser\Foundation\Grammar\Compiled\Model\CompiledGrammar;
use PhpArchitecture\Parser\Foundation\Tokenization\Contract\TokenizationContext;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\TokenMatchedEvent;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\TokenRegionEndedEvent;
use PhpArchitecture\Parser\Foundation\Tokenization\Extension\IdentifyRowsAndColumns;
use ReflectionProperty;

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

        $rootRegion = $grammar->regions[$grammar->rootRegionName];
        
        $context = new DefaultTokenizationContext(
            rootName: $grammar->rootRegionName,
            applyBofEof: $grammar->requireBofEof,
            regionToPatternLibraryMap: $patternLibraries,
            regionToEventDispatcherMap: [],
            rootRegionTags: $rootRegion->tags,
        );

        if ($applyRowColTracking) {
            $context->setMeta('currentRow', 1);
            $context->setMeta('currentColumn', 1);
        }

        $applyRowColTrackingInstance = new IdentifyRowsAndColumns();
        foreach ($grammar->regions as $regionName => $region) {
            $dispatcher = new TokenizationEventDispatcher($context);

            foreach ($region->eventSubscribers as $subscriber) {
                $dispatcher->registerEventListener(
                    $subscriber->listener,
                    $subscriber->eventClassName,
                    $subscriber->onlyForRuleName,
                );
            }

            if ($applyRowColTracking) {
                $dispatcher->registerEventListener($applyRowColTrackingInstance, TokenMatchedEvent::class);
                $dispatcher->registerEventListener($applyRowColTrackingInstance, TokenRegionEndedEvent::class);
            }

            $eventDispatchers[$regionName] = $dispatcher;
        }

        $reflection = new ReflectionProperty($context, 'regionToEventDispatcherMap');
        $reflection->setValue($context, $eventDispatchers);

        $dispatcherReflection = new ReflectionProperty($context, 'dispatcher');
        $rootDispatcher = $eventDispatchers[$grammar->rootRegionName] ?? reset($eventDispatchers);
        $dispatcherReflection->setValue($context, $rootDispatcher);

        return $context;
    }
}
