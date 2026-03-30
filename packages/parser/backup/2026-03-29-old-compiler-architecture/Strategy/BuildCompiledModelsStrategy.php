<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Compiled\Strategy;

use PhpArchitecture\Parser\Grammar\Compiled\Internal\WorkingRegion;
use PhpArchitecture\Parser\Grammar\Compiled\Model\CompiledGrammar;
use PhpArchitecture\Parser\Grammar\Compiled\Model\EventSubscriber as CompiledEventSubscriber;
use PhpArchitecture\Parser\Grammar\Compiled\Model\Region as CompiledRegion;
use PhpArchitecture\Parser\Grammar\Compiled\Model\Rule as CompiledRule;
use PhpArchitecture\Parser\Grammar\Definition\Grammar;

final class BuildCompiledModelsStrategy implements CompilerStrategyInterface
{
    /**
     * @param array{0: Grammar, 1: array<string, WorkingRegion>} $input
     * @return CompiledGrammar
     */
    public function execute(mixed $input): CompiledGrammar
    {
        [$grammar, $workingRegions] = $input;
        
        $compiledRegions = [];
        
        foreach ($workingRegions as $name => $working) {
            $compiledRules = [];
            foreach ($working->rules as $rule) {
                $compiledRules[] = new CompiledRule(
                    name: $rule->name,
                    type: $rule->type,
                    definition: $rule->definition,
                    tags: $rule->tags,
                    priority: $rule->priority,
                );
            }
            
            $compiledSubscribers = [];
            foreach ($working->eventSubscribers as $subscriber) {
                $compiledSubscribers[] = new CompiledEventSubscriber(
                    eventClassName: $subscriber->eventClassName,
                    listener: $subscriber->listener,
                    onlyForRuleName: $subscriber->onlyForRuleName,
                    priority: $subscriber->priority,
                );
            }
            
            usort($compiledSubscribers, fn($a, $b) => $b->priority <=> $a->priority);
            
            $compiledRegions[$name] = new CompiledRegion(
                name: $name,
                rules: $compiledRules,
                eventSubscribers: $compiledSubscribers,
                parentRegionName: $working->parentName,
                metadata: $working->metadata,
            );
        }
        
        return new CompiledGrammar(
            name: $grammar->name,
            variant: $grammar->variant,
            requireBofEof: $grammar->requireBofEof,
            regions: $compiledRegions,
        );
    }
}
