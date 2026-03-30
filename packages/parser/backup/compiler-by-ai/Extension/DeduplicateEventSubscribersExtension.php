<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Compiled\Extension;

use PhpArchitecture\Parser\Grammar\Definition\Grammar;

/**
 * Deduplicates event subscribers based on their hash.
 * Must run before ConvertClosuresToListenersExtension.
 */
final class DeduplicateEventSubscribersExtension implements CompilerExtensionInterface
{
    public function apply(Grammar $grammar): void
    {
        $allRegions = $grammar->getAllRegions();

        foreach ($allRegions as $region) {
            $deduplicated = [];
            
            foreach ($region->eventSubscribers as $subscriber) {
                $hash = $subscriber->hash();
                
                if (!isset($deduplicated[$hash])) {
                    $deduplicated[$hash] = $subscriber;
                }
            }
            
            // Replace event subscribers with deduplicated ones using reflection
            $reflection = new \ReflectionProperty($region, 'eventSubscribers');
            $reflection->setValue($region, array_values($deduplicated));
        }
    }

    public function priority(): int
    {
        return 750;
    }
}
