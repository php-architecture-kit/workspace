<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Compiled\Extension;

use PhpArchitecture\Parser\Grammar\Compiled\Model\EventSubscriber;
use PhpArchitecture\Parser\Grammar\Compiled\Model\Region;
use PhpArchitecture\Parser\Grammar\Definition\EventListener\Tokenization\EndRegionEventListener;
use PhpArchitecture\Parser\Grammar\Definition\EventListener\Tokenization\StartRegionEventListener;
use PhpArchitecture\Parser\Grammar\Definition\EventSubscriber as DefinitionEventSubscriber;
use PhpArchitecture\Parser\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Grammar\Definition\Region as DefinitionRegion;
use PhpArchitecture\Parser\Processing\Event\Tokenization\TokenAddedEvent;
use PhpArchitecture\Parser\Processing\Event\Tokenization\TokenMatchedEvent;
use RuntimeException;

/**
 * Processes Region.config.openRule and closeRule by:
 * - Resolving string references to actual Rule objects
 * - Adding rules to appropriate regions (open rule to parent, close rule to child)
 * - Creating EventSubscribers for region start/end
 */
final class OpenCloseRuleExtension implements CompilerExtensionInterface
{
    public function apply(Grammar $grammar): void
    {
        $allRegions = $grammar->getAllRegions();

        foreach ($allRegions as $region) {
            $parentRegion = $this->findParentRegion($region, $allRegions);
            
            $this->processOpenRule($region, $parentRegion);
            $this->processCloseRule($region);
        }
    }

    private function processOpenRule(
        DefinitionRegion $region,
        ?DefinitionRegion $parentRegion
    ): void {
        $openRule = $region->config->openRule;
        if ($openRule === null) {
            return;
        }

        // Resolve string reference to Rule object
        if (is_string($openRule)) {
            if ($parentRegion === null) {
                throw new RuntimeException("Cannot resolve open rule string reference for region `{$region->name}` without parent region");
            }
            
            if (!isset($parentRegion->rules[$openRule])) {
                throw new RuntimeException("Missing rule declaration `{$openRule}`, required by region `{$region->name}` open rule");
            }

            $openRule = $parentRegion->rules[$openRule];
            $region->config->openRule = $openRule;
        }

        if (!$openRule instanceof Rule) {
            return;
        }

        // Add open rule to parent region if needed
        if ($parentRegion !== null) {
            $parentRegion->add($openRule);
        }

        // Add open rule to child region if includeOpenRule is true
        if ($region->config->includeOpenRule) {
            $region->add($openRule);
        }

        // Add EventSubscriber to parent region to start child region
        if ($parentRegion !== null) {
            $eventClass = $region->config->includeOpenRuleMatch 
                ? TokenMatchedEvent::class 
                : TokenAddedEvent::class;
            
            $parentRegion->add(
                DefinitionEventSubscriber::on(
                    $eventClass,
                    new StartRegionEventListener($region, $openRule)
                )
            );
        }

        // Handle closeAfterOpenRuleMatch
        if ($region->config->closeAfterOpenRuleMatch) {
            $region->add(
                DefinitionEventSubscriber::on(
                    TokenAddedEvent::class,
                    new EndRegionEventListener($openRule, false, true, false)
                )
            );
        }
    }

    private function processCloseRule(\PhpArchitecture\Parser\Grammar\Definition\Region $region): void
    {
        $closeRule = $region->config->closeRule;
        if ($closeRule === null) {
            return;
        }

        // Resolve string reference to Rule object
        if (is_string($closeRule)) {
            if (!isset($region->rules[$closeRule])) {
                throw new RuntimeException("Missing rule declaration `{$closeRule}`, required by region `{$region->name}` close rule");
            }

            $closeRule = $region->rules[$closeRule];
            $region->config->closeRule = $closeRule;
        }

        if (!$closeRule instanceof Rule) {
            return;
        }

        // Add close rule to region
        $region->add($closeRule);

        // Add EventSubscriber to close region
        $eventClass = $region->config->includeCloseRuleMatch 
            ? TokenAddedEvent::class 
            : TokenMatchedEvent::class;
        
        // For negated rules, callLastTokenRemoval logic is inverted:
        // - includeCloseRuleMatch=true means token that doesn't match IS in region -> should be removed
        // - includeCloseRuleMatch=false means token that doesn't match is NOT in region -> don't remove
        $callLastTokenRemoval = $region->config->closeWhenCloseRuleNotMatch
            ? $region->config->includeCloseRuleMatch
            : !$region->config->includeCloseRuleMatch;
        
        $region->add(
            DefinitionEventSubscriber::on(
                $eventClass,
                new EndRegionEventListener(
                    $closeRule,
                    $region->config->closeWhenCloseRuleNotMatch,
                    false,
                    $callLastTokenRemoval
                )
            )
        );
    }

    /**
     * @param array<string, DefinitionRegion> $allRegions
     */
    private function findParentRegion(
        DefinitionRegion $region,
        array $allRegions
    ): ?DefinitionRegion {
        foreach ($allRegions as $candidate) {
            if (isset($candidate->regions[$region->name])) {
                return $candidate;
            }
        }
        return null;
    }

    public function priority(): int
    {
        return 50;
    }
}
