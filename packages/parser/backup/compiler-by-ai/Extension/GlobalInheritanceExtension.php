<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Compiled\Extension;

use PhpArchitecture\Parser\Grammar\Definition\Grammar;

/**
 * Applies global rule and event subscriber inheritance.
 * All regions can inherit from the global region based on config flags.
 */
final class GlobalInheritanceExtension implements CompilerExtensionInterface
{
    public function apply(Grammar $grammar): void
    {
        $allRegions = $grammar->getAllRegions();

        foreach ($allRegions as $region) {
            if ($region === $grammar->global) {
                continue;
            }

            if (!$region->config->includeGlobalRules && !$region->config->includeGlobalEventSubscribers) {
                continue;
            }

            if ($region->config->includeGlobalRules) {
                foreach ($grammar->global->rules as $rule) {
                    if (isset($region->rules[$rule->name])) {
                        continue;
                    }
                    $region->add($rule);
                }
            }

            if ($region->config->includeGlobalEventSubscribers) {
                foreach ($grammar->global->eventSubscribers as $eventSubscriber) {
                    $region->add($eventSubscriber);
                }
            }
        }
    }

    public function priority(): int
    {
        return 600;
    }
}
