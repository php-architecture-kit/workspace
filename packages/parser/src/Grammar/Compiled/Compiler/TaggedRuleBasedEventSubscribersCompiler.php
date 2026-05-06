<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Compiled\Compiler;

use PhpArchitecture\Parser\Grammar\Definition\EventSubscriber;
use PhpArchitecture\Parser\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Grammar\Definition\Model\RuleType;
use PhpArchitecture\Parser\Grammar\Definition\Model\Technical\TaggedRule;
use PhpArchitecture\Parser\Grammar\Definition\Region;
use PhpArchitecture\Parser\Grammar\Definition\Rule;

class TaggedRuleBasedEventSubscribersCompiler implements GrammarCompilerInterface
{
    public function compileGrammar(Grammar $grammar): void
    {
        $allRegions = $grammar->getAllRegions();

        foreach ($allRegions as $region) {
            $this->compileRegion($region);
        }
    }

    public function compileRegion(Region $region): void
    {
        $taggedRulesInRegion = $this->getTaggedRulesInRegion($region);
        $eventSubscribersToChange = $this->getRuleBasedEventSubscribers($region, array_keys($taggedRulesInRegion));

        foreach ($eventSubscribersToChange as $esHash => $eventSubscriber) {
            $rule = $taggedRulesInRegion[$eventSubscriber->onlyForRuleName];
            assert($rule !== null, 'Tagged rule should exist');
            $taggedRuleDef = $rule->definition;
            assert($taggedRuleDef instanceof TaggedRule);

            foreach ($taggedRuleDef->getTaggedRules() as $taggedRule) {
                $newEventSubscriber = new EventSubscriber(
                    $eventSubscriber->eventClassName,
                    $eventSubscriber->listener,
                    $taggedRule->name,
                    $eventSubscriber->priority,
                );

                $region->addEventSubscriber($newEventSubscriber, false);
            }
        }
    }

    /** @return array<string,Rule> */
    private function getTaggedRulesInRegion(Region $region): array
    {
        return array_filter(
            $region->rules,
            fn(Rule $rule) => $rule->type === RuleType::Tag,
        );
    }

    /** 
     * @param string[] $wantedRules
     * @return array<string,EventSubscriber> 
     */
    private function getRuleBasedEventSubscribers(Region $region, array $wantedRules): array
    {
        return array_filter(
            $region->eventSubscribers,
            static fn(EventSubscriber $es) => $es->onlyForRuleName !== null && in_array($es->onlyForRuleName, $wantedRules, true),
        );
    }
}
