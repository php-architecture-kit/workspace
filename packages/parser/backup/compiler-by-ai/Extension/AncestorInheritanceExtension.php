<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Compiled\Extension;

use PhpArchitecture\Parser\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Grammar\Definition\Region;

/**
 * Applies ancestor rule and event subscriber inheritance.
 * Child regions inherit from their parent region based on config flags.
 */
final class AncestorInheritanceExtension implements CompilerExtensionInterface
{
    public function apply(Grammar $grammar): void
    {
        $allRegions = $grammar->getAllRegions();

        foreach ($allRegions as $region) {
            if (!$region->config->includeAncestorRules && !$region->config->includeAncestorEventSubscribers) {
                continue;
            }

            $parentRegion = $this->findParentRegion($region, $allRegions);
            if ($parentRegion === null) {
                continue;
            }

            // Skip if parent is global - GlobalInheritanceExtension handles that
            if ($parentRegion === $grammar->global) {
                continue;
            }

            if ($region->config->includeAncestorRules) {
                foreach ($parentRegion->rules as $rule) {
                    if (isset($region->rules[$rule->name])) {
                        continue;
                    }
                    $region->add($rule);
                }
            }

            if ($region->config->includeAncestorEventSubscribers) {
                foreach ($parentRegion->eventSubscribers as $eventSubscriber) {
                    $region->add($eventSubscriber);
                }
            }
        }
    }

    /**
     * @param array<string, Region> $allRegions
     */
    private function findParentRegion(
        Region $region,
        array $allRegions
    ): ?Region {
        foreach ($allRegions as $candidate) {
            if (isset($candidate->regions[$region->name])) {
                return $candidate;
            }
        }
        return null;
    }

    public function priority(): int
    {
        return 500;
    }
}
