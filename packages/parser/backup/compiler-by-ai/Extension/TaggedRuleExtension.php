<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Compiled\Extension;

use PhpArchitecture\Parser\Grammar\Definition\EventListener\Tokenization\EndRegionEventListener;
use PhpArchitecture\Parser\Grammar\Definition\EventListener\Tokenization\StartRegionEventListener;
use PhpArchitecture\Parser\Grammar\Definition\EventSubscriber;
use PhpArchitecture\Parser\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Grammar\Definition\Model\Tag\TaggedRule;
use PhpArchitecture\Parser\Grammar\Definition\Model\RuleType;
use PhpArchitecture\Parser\Processing\Event\Tokenization\TokenAddedEvent;
use PhpArchitecture\Parser\Processing\Event\Tokenization\TokenMatchedEvent;

/**
 * Resolves Rule::taggedWith() by finding all rules with matching tag
 * and converting tagged rule to either:
 * - EventSubscriber that starts region when any tagged rule matches (for startRegion)
 * - Choice rule containing all tagged rules (future implementation)
 */
final class TaggedRuleExtension implements CompilerExtensionInterface
{
    public function apply(Grammar $grammar): void
    {
        $allRegions = $grammar->getAllRegions();

        foreach ($allRegions as $region) {
            $taggedRules = [];
            
            foreach ($region->rules as $ruleName => $rule) {
                if ($rule->type === RuleType::Tag) {
                    $taggedRules[$ruleName] = $rule;
                }
            }

            foreach ($taggedRules as $ruleName => $taggedRule) {
                /** @var TaggedRule $definition */
                $definition = $taggedRule->definition;
                $tag = $definition->tag;

                // Find all rules in this region with matching tag
                $matchingRules = [];
                foreach ($region->rules as $candidateRule) {
                    if ($candidateRule->hasTag($tag) && $candidateRule !== $taggedRule) {
                        $matchingRules[] = $candidateRule;
                    }
                }

                // Check if this tagged rule is used to start a region
                $startsRegion = false;
                foreach ($region->regions as $childRegion) {
                    if ($childRegion->config->openRule === $taggedRule) {
                        $startsRegion = true;
                        
                        // Add EventSubscriber for each matching rule to start the region
                        foreach ($matchingRules as $matchingRule) {
                            $eventClass = $childRegion->config->includeOpenRuleMatch 
                                ? TokenMatchedEvent::class 
                                : TokenAddedEvent::class;
                            
                            $region->add(
                                EventSubscriber::on(
                                    $eventClass,
                                    new StartRegionEventListener($childRegion, $matchingRule)
                                )
                            );
                        }
                    }
                }

                // If not used for region start, could be converted to Choice rule
                // For now, we just remove the tagged rule placeholder
                if (!$startsRegion) {
                    // Future: convert to Choice rule
                    // Use reflection to remove from private(set) array
                    $reflection = new \ReflectionProperty($region, 'rules');
                    $rules = $reflection->getValue($region);
                    unset($rules[$ruleName]);
                    $reflection->setValue($region, $rules);
                }
            }
        }
    }

    public function priority(): int
    {
        return 200;
    }
}
