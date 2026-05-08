<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Grammar\Compiled\Compiler;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Region;

class RegionOpenerCloserCompiler implements GrammarCompilerInterface
{
    public function compileGrammar(Grammar $grammar): void
    {
        $allRegions = $grammar->getAllRegions();

        foreach ($allRegions as $region) {
            if ($region->config->closer !== null) {
                $region->add($region->config->closer);
            }

            if ($region->config->opener !== null) {
                $parentRegion = $this->findParentRegion($region, $allRegions);
                
                if ($parentRegion !== null) {
                    $parentRegion->add($region->config->opener);
                    
                    $openerRuleName = $region->config->opener->onlyForRuleName;
                    if ($openerRuleName !== null) {
                        $openerRule = $this->findRuleInRegion($region, $openerRuleName);
                        if ($openerRule !== null) {
                            $parentRegion->add($openerRule);
                        }
                    }
                }
            }
        }
    }

    private function findRuleInRegion(Region $region, string $ruleName): ?object
    {
        foreach ($region->rules as $rule) {
            if ($rule->name === $ruleName) {
                return $rule;
            }
        }
        
        return null;
    }

    /**
     * @param array<string,Region> $allRegions
     */
    private function findParentRegion(Region $targetRegion, array $allRegions): ?Region
    {
        foreach ($allRegions as $region) {
            if (isset($region->regions[$targetRegion->name])) {
                return $region;
            }
        }

        return null;
    }
}
