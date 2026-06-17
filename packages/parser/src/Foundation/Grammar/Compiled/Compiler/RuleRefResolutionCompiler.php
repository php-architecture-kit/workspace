<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Grammar\Compiled\Compiler;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Ref\RefRuleDefinition;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Region;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use RuntimeException;

class RuleRefResolutionCompiler implements GrammarPrecompilerInterface
{
    public function precompileGrammar(Grammar $grammar): void
    {
        $allRegions = $grammar->getAllRegions();
        $parentMap = $this->buildParentMap($allRegions);

        foreach ($allRegions as $region) {
            foreach ($region->rules as $rule) {
                if (!($rule->definition instanceof RefRuleDefinition)) {
                    continue;
                }

                $resolved = $this->resolve($rule->definition->refName, $region, $parentMap, $grammar->global);

                if ($resolved === null) {
                    throw new RuntimeException(
                        "Rule::ref('{$rule->definition->refName}') in region '{$region->name}' could not be resolved. "
                        . "No rule named '{$rule->definition->refName}' found in inner grammar, ancestor regions, or global region."
                    );
                }

                foreach ($rule->eventSubscribers as $subscriber) {
                    $region->addEventSubscriber($subscriber);
                }

                $region->addRule($resolved, false);
            }
        }
    }

    /** @param array<string, Region> $allRegions */
    private function buildParentMap(array $allRegions): array
    {
        $parentMap = [];

        foreach ($allRegions as $region) {
            foreach ($region->regions as $childName => $child) {
                $parentMap[$childName] = $region;
            }
        }

        return $parentMap;
    }

    /** @param array<string, Region> $parentMap */
    private function resolve(string $refName, Region $region, array $parentMap, Region $global): ?Rule
    {
        $innerGrammar = $region->config->innerGrammar;
        if ($innerGrammar !== null && $region->config->retokenizeWithInnerGrammar === false) {
            $innerRegions = $innerGrammar->getAllRegions();
            foreach ($innerRegions as $innerRegion) {
                if (isset($innerRegion->rules[$refName])) {
                    $candidate = $innerRegion->rules[$refName];
                    if (!($candidate->definition instanceof RefRuleDefinition)) {
                        return $candidate;
                    }
                }
            }
        }

        $current = $parentMap[$region->name] ?? null;
        while ($current !== null && $current->name !== $global->name) {
            if (isset($current->rules[$refName])) {
                $candidate = $current->rules[$refName];
                if (!($candidate->definition instanceof RefRuleDefinition)) {
                    return $candidate;
                }
            }
            $current = $parentMap[$current->name] ?? null;
        }

        if (isset($global->rules[$refName])) {
            $candidate = $global->rules[$refName];
            if (!($candidate->definition instanceof RefRuleDefinition)) {
                return $candidate;
            }
        }

        return null;
    }
}
