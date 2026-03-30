<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Compiled\Extension;

use PhpArchitecture\Parser\Grammar\Definition\EventListener\Tokenization\DynamicTokenInitEventListener;
use PhpArchitecture\Parser\Grammar\Definition\EventSubscriber;
use PhpArchitecture\Parser\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Grammar\Definition\Model\Regex\CallbackRule;
use PhpArchitecture\Parser\Grammar\Definition\Model\RuleType;
use PhpArchitecture\Parser\Grammar\Definition\Region;
use PhpArchitecture\Parser\Processing\Event\Tokenization\TokenAddedEvent;
use RuntimeException;

/**
 * Processes Rule::dynamic() by adding DynamicTokenInitEventListener
 * to target regions specified in CallbackRule.listenInRegions.
 */
final class DynamicTokenExtension implements CompilerExtensionInterface
{
    public function apply(Grammar $grammar): void
    {
        $allRegions = $grammar->getAllRegions();

        foreach ($allRegions as $region) {
            foreach ($region->rules as $ruleName => $rule) {
                if ($rule->type !== RuleType::DynamicToken) {
                    continue;
                }

                /** @var CallbackRule $callbackDefinition */
                $callbackDefinition = $rule->definition;
                $dynamicTokenKey = 'dynamic_token_' . $ruleName . '_from_' . $region->name;
                
                $eventSubscriber = EventSubscriber::on(
                    TokenAddedEvent::class,
                    new DynamicTokenInitEventListener($callbackDefinition->triggerRule, $rule, $region)
                );

                foreach ($callbackDefinition->listenInRegions as $regionName) {
                    $targetRegions = $this->resolveTargetRegions($regionName, $grammar, $region, $allRegions);

                    foreach ($targetRegions as $targetRegion) {
                        if ($targetRegion === null) {
                            throw new RuntimeException("Region `{$regionName}` not found for rule `{$ruleName}`");
                        }

                        // Use metadata to prevent duplicate registration
                        if (!$targetRegion->hasMeta($dynamicTokenKey)) {
                            $targetRegion->add($eventSubscriber);
                            $targetRegion->setMeta($dynamicTokenKey, true);
                        }
                    }
                }
            }
        }
    }

    /**
     * @param array<string, Region> $allRegions
     * @return array<Region|null>
     */
    private function resolveTargetRegions(
        string $regionName,
        Grammar $grammar,
        Region $currentRegion,
        array $allRegions
    ): array {
        return match ($regionName) {
            CallbackRule::GLOBAL_REGION => [$grammar->global],
            CallbackRule::PARENT_REGION => $this->findParentRegion($currentRegion, $allRegions),
            CallbackRule::ROOT_REGION => isset($grammar->rootRegion) ? [$grammar->rootRegion] : [$grammar->global],
            CallbackRule::SAME_REGION => [$currentRegion],
            CallbackRule::LISTEN_IN_ALL_REGIONS => array_values($allRegions),
            default => isset($allRegions[$regionName]) ? [$allRegions[$regionName]] : [],
        };
    }

    /**
     * @param array<string, Region> $allRegions
     * @return array<Region|null>
     */
    private function findParentRegion(
        Region $region,
        array $allRegions
    ): array {
        foreach ($allRegions as $candidate) {
            if (isset($candidate->regions[$region->name])) {
                return [$candidate];
            }
        }
        return [];
    }

    public function priority(): int
    {
        return 300;
    }
}
