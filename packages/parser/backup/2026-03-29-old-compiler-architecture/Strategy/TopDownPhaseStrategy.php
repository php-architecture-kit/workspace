<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Compiled\Strategy;

use PhpArchitecture\Parser\Grammar\Compiled\Internal\WorkingRegion;
use PhpArchitecture\Parser\Grammar\Definition\EventSubscriber;
use PhpArchitecture\Parser\Grammar\Definition\EventListener\Tokenization\DynamicTokenInitEventListener;
use PhpArchitecture\Parser\Grammar\Definition\EventListener\Tokenization\EndRegionEventListener;
use PhpArchitecture\Parser\Grammar\Definition\EventListener\Tokenization\StartRegionEventListener;
use PhpArchitecture\Parser\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Grammar\Definition\Model\Regex\CallbackRule;
use PhpArchitecture\Parser\Grammar\Definition\Model\RuleType;
use PhpArchitecture\Parser\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Processing\Event\Tokenization\TokenAddedEvent;
use PhpArchitecture\Parser\Processing\Event\Tokenization\TokenMatchedEvent;
use RuntimeException;

final class TopDownPhaseStrategy implements CompilerStrategyInterface
{
    /**
     * @param array{0: Grammar, 1: array<string, WorkingRegion>} $input
     * @return array<string, WorkingRegion>
     */
    public function execute(mixed $input): array
    {
        [$grammar, $workingRegions] = $input;
        
        $this->processTopDown($grammar, $grammar->global->name, $workingRegions);
        
        return $workingRegions;
    }

    /**
     * @param array<string, WorkingRegion> $workingRegions
     */
    private function processTopDown(Grammar $grammar, string $regionName, array $workingRegions): void
    {
        $working = $workingRegions[$regionName];
        
        $working->source->config->assertValid();

        $this->applyIncludeAncestorRules($working, $workingRegions);
        $this->applyIncludeAncestorEventSubscribers($working, $workingRegions);
        $this->applyIncludeGlobalRules($working, $workingRegions);
        $this->applyIncludeGlobalEventSubscribers($working, $workingRegions);

        $this->applyDynamicTokens($grammar, $working, $workingRegions);

        $this->applyOpenRuleTopDown($working, $workingRegions);
        $this->applyCloseRuleTopDown($working);

        foreach ($working->source->regions as $childName => $childRegion) {
            $this->processTopDown($grammar, $childName, $workingRegions);
        }
    }

    /**
     * @param array<string, WorkingRegion> $workingRegions
     */
    private function applyIncludeAncestorRules(WorkingRegion $working, array $workingRegions): void
    {
        if (!$working->source->config->includeAncestorRules || $working->parentName === null) {
            return;
        }

        $ancestor = $workingRegions[$working->parentName];
        
        foreach ($ancestor->rules as $rule) {
            if (isset($working->rules[$rule->name])) {
                continue;
            }

            $working->addRule($rule);
        }
    }

    /**
     * @param array<string, WorkingRegion> $workingRegions
     */
    private function applyIncludeAncestorEventSubscribers(WorkingRegion $working, array $workingRegions): void
    {
        if (!$working->source->config->includeAncestorEventSubscribers || $working->parentName === null) {
            return;
        }

        $ancestor = $workingRegions[$working->parentName];
        
        foreach ($ancestor->eventSubscribers as $eventSubscriber) {
            $working->addEventSubscriber($eventSubscriber);
        }
    }

    /**
     * @param array<string, WorkingRegion> $workingRegions
     */
    private function applyIncludeGlobalRules(WorkingRegion $working, array $workingRegions): void
    {
        if (!$working->source->config->includeGlobalRules) {
            return;
        }

        $global = $workingRegions['global'];
        
        foreach ($global->rules as $rule) {
            if (isset($working->rules[$rule->name])) {
                continue;
            }

            $working->addRule($rule);
        }
    }

    /**
     * @param array<string, WorkingRegion> $workingRegions
     */
    private function applyIncludeGlobalEventSubscribers(WorkingRegion $working, array $workingRegions): void
    {
        if (!$working->source->config->includeGlobalEventSubscribers) {
            return;
        }

        $global = $workingRegions['global'];
        
        foreach ($global->eventSubscribers as $eventSubscriber) {
            $working->addEventSubscriber($eventSubscriber);
        }
    }

    /**
     * @param array<string, WorkingRegion> $workingRegions
     */
    private function applyDynamicTokens(Grammar $grammar, WorkingRegion $working, array $workingRegions): void
    {
        foreach ($working->rules as $ruleName => $rule) {
            if ($rule->type !== RuleType::DynamicToken) {
                continue;
            }

            /** @var CallbackRule $callbackDefinition */
            $callbackDefinition = $rule->definition;
            $dynamicTokenKey = 'dynamic_token_' . $ruleName . '_from_' . $working->source->name;
            $eventSubscriber = EventSubscriber::on(
                TokenAddedEvent::class,
                new DynamicTokenInitEventListener($callbackDefinition->triggerRule, $rule, $working->source)
            );

            foreach ($callbackDefinition->listenInRegions as $regionName) {
                $targetRegions = $this->resolveTargetRegions(
                    $regionName,
                    $grammar,
                    $working,
                    $workingRegions
                );

                foreach ($targetRegions as $targetRegion) {
                    if (!$targetRegion->hasMeta($dynamicTokenKey)) {
                        $targetRegion->addEventSubscriber($eventSubscriber);
                        $targetRegion->setMeta($dynamicTokenKey, true);
                    }
                }
            }
        }
    }

    /**
     * @param array<string, WorkingRegion> $workingRegions
     * @return WorkingRegion[]
     */
    private function resolveTargetRegions(
        string $regionName,
        Grammar $grammar,
        WorkingRegion $working,
        array $workingRegions
    ): array {
        return match ($regionName) {
            CallbackRule::GLOBAL_REGION => [$workingRegions['global']],
            CallbackRule::PARENT_REGION => $working->parentName !== null ? [$workingRegions[$working->parentName]] : [],
            CallbackRule::ROOT_REGION => isset($grammar->rootRegion) ? [$workingRegions[$grammar->rootRegion->name]] : [],
            CallbackRule::SAME_REGION => [$working],
            CallbackRule::LISTEN_IN_ALL_REGIONS => array_values($workingRegions),
            default => isset($workingRegions[$regionName]) ? [$workingRegions[$regionName]] : throw new RuntimeException("Region `{$regionName}` not found"),
        };
    }

    /**
     * @param array<string, WorkingRegion> $workingRegions
     */
    private function applyOpenRuleTopDown(WorkingRegion $working, array $workingRegions): void
    {
        $openRule = $working->source->config->openRule;
        if ($openRule === null) {
            return;
        }

        if (is_string($openRule)) {
            if ($working->parentName === null) {
                throw new RuntimeException("Missing parent region for string reference open rule in region `{$working->source->name}`.");
            }

            $ancestor = $workingRegions[$working->parentName];
            
            if (!isset($ancestor->rules[$openRule])) {
                throw new RuntimeException("Missing rule declaration, required by region `{$working->source->name}` open rule.");
            }

            $openRule = $ancestor->rules[$openRule];
        }

        if ($working->parentName !== null) {
            $ancestor = $workingRegions[$working->parentName];
            $ancestor->addEventSubscriber(
                EventSubscriber::on(
                    $working->source->config->includeOpenRuleMatch ? TokenMatchedEvent::class : TokenAddedEvent::class,
                    new StartRegionEventListener($working->source, $openRule)
                )
            );
        }

        if ($working->source->config->closeAfterOpenRuleMatch) {
            $working->addEventSubscriber(
                EventSubscriber::on(
                    TokenAddedEvent::class,
                    new EndRegionEventListener($openRule, false, true)
                )
            );
        }
    }

    private function applyCloseRuleTopDown(WorkingRegion $working): void
    {
        $closeRule = $working->source->config->closeRule;
        if ($closeRule === null) {
            return;
        }

        if (is_string($closeRule)) {
            if (!isset($working->rules[$closeRule])) {
                throw new RuntimeException("Missing rule declaration, required by region `{$working->source->name}` close rule.");
            }

            $closeRule = $working->rules[$closeRule];
        }

        $working->addEventSubscriber(
            EventSubscriber::on(
                $working->source->config->includeCloseRuleMatch ? TokenAddedEvent::class : TokenMatchedEvent::class,
                new EndRegionEventListener($closeRule, $working->source->config->closeWhenCloseRuleNotMatch, false)
            )
        );
    }
}
