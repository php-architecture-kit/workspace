<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Compiled\Strategy;

use PhpArchitecture\Parser\Grammar\Compiled\Internal\WorkingRegion;
use PhpArchitecture\Parser\Grammar\Definition\EventSubscriber;
use PhpArchitecture\Parser\Grammar\Definition\EventListener\Tokenization\RetokenizeRegionEventListener;
use PhpArchitecture\Parser\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Processing\Event\Tokenization\TokenRegionEndedEvent;
use RuntimeException;

final class DownTopPhaseStrategy implements CompilerStrategyInterface
{
    /**
     * @param array{0: Grammar, 1: array<string, WorkingRegion>} $input
     * @return array<string, WorkingRegion>
     */
    public function execute(mixed $input): array
    {
        [$grammar, $workingRegions] = $input;
        
        $this->processDownTop($grammar->global->name, $workingRegions);
        
        return $workingRegions;
    }

    /**
     * @param array<string, WorkingRegion> $workingRegions
     */
    private function processDownTop(string $regionName, array $workingRegions): void
    {
        $working = $workingRegions[$regionName];
        
        foreach ($working->source->regions as $childName => $childRegion) {
            $this->processDownTop($childName, $workingRegions);
        }

        $this->applyInsideGrammar($working, $workingRegions);
        $this->applyOpenRuleDownTop($working, $workingRegions);
        $this->applyCloseRuleDownTop($working);
    }

    /**
     * @param array<string, WorkingRegion> $workingRegions
     */
    private function applyInsideGrammar(WorkingRegion $working, array $workingRegions): void
    {
        $insideGrammar = $working->source->config->insideGrammar;
        if ($insideGrammar === null) {
            return;
        }

        if ($working->source->config->retokenizeWithInsideGrammar) {
            $working->addEventSubscriber(
                EventSubscriber::on(
                    TokenRegionEndedEvent::class,
                    new RetokenizeRegionEventListener($working->source)
                )
            );
        } else {
            if ((!empty($working->rules) || !empty($working->source->regions)) 
                && !$working->source->config->confirmMixOfRegionRulesAndInsideGrammarRules) {
                throw new RuntimeException('Your grammar configuration is going to mix inside grammar with the new rules. You have to confirm that you want to do this by setting confirmMixOfRegionRulesAndInsideGrammarRules to true in the region config.');
            }

            $insideWorkingRegions = (new ValidateAndFlattenStrategy())->execute($insideGrammar);
            $insideWorkingRegions = (new DownTopPhaseStrategy())->execute([$insideGrammar, $insideWorkingRegions]);
            $insideWorkingRegions = (new TopDownPhaseStrategy())->execute([$insideGrammar, $insideWorkingRegions]);

            $insideGlobal = $insideWorkingRegions['global'];

            foreach ($insideGlobal->rules as $rule) {
                if (!isset($working->rules[$rule->name])) {
                    $working->addRule($rule);
                }
            }

            foreach ($insideGlobal->eventSubscribers as $subscriber) {
                $working->addEventSubscriber($subscriber);
            }
        }
    }

    /**
     * @param array<string, WorkingRegion> $workingRegions
     */
    private function applyOpenRuleDownTop(WorkingRegion $working, array $workingRegions): void
    {
        $openRule = $working->source->config->openRule;
        if ($openRule === null) {
            return;
        }

        if ($openRule instanceof Rule) {
            if ($working->source->config->includeOpenRule) {
                $working->addRule($openRule);
            }

            if ($working->parentName !== null) {
                $parent = $workingRegions[$working->parentName];
                $parent->addRule($openRule);
            }
        }
    }

    private function applyCloseRuleDownTop(WorkingRegion $working): void
    {
        $closeRule = $working->source->config->closeRule;
        if ($closeRule === null) {
            return;
        }

        if ($closeRule instanceof Rule) {
            $working->addRule($closeRule);
        }
    }
}
