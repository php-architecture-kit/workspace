<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Compiled\Extension;

use PhpArchitecture\Parser\Grammar\Definition\EventListener\Tokenization\RetokenizeRegionEventListener;
use PhpArchitecture\Parser\Grammar\Definition\EventSubscriber;
use PhpArchitecture\Parser\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Processing\Event\Tokenization\TokenRegionEndedEvent;
use RuntimeException;

/**
 * Processes Region.config.insideGrammar by either:
 * - Merging inside grammar rules/regions/subscribers into current region
 * - Adding retokenization event subscriber
 */
final class InsideGrammarExtension implements CompilerExtensionInterface
{
    public function apply(Grammar $grammar): void
    {
        $allRegions = $grammar->getAllRegions();

        foreach ($allRegions as $region) {
            if ($region->config->insideGrammar === null) {
                continue;
            }

            $insideGrammar = $region->config->insideGrammar;

            if ($region->config->retokenizeWithInsideGrammar) {
                $region->add(
                    EventSubscriber::on(
                        TokenRegionEndedEvent::class,
                        new RetokenizeRegionEventListener($region)
                    )
                );
            } else {
                if ((!empty($region->rules) || !empty($region->regions)) 
                    && !$region->config->confirmMixOfRegionRulesAndInsideGrammarRules) {
                    throw new RuntimeException(
                        'Grammar configuration will mix inside grammar with existing rules. ' .
                        'Set confirmMixOfRegionRulesAndInsideGrammarRules to true in region config to confirm.'
                    );
                }

                // Add inside grammar rules first (lower priority)
                foreach ($insideGrammar->global->rules as $rule) {
                    if (!isset($region->rules[$rule->name])) {
                        $region->add($rule);
                    }
                }

                // Add inside grammar regions
                foreach ($insideGrammar->global->regions as $childRegion) {
                    if (!isset($region->regions[$childRegion->name])) {
                        $region->add($childRegion);
                    }
                }

                // Add inside grammar event subscribers
                foreach ($insideGrammar->global->eventSubscribers as $subscriber) {
                    $region->add($subscriber);
                }
            }
        }
    }

    public function priority(): int
    {
        return 700;
    }
}
