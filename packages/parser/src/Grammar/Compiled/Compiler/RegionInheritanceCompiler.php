<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Compiled\Compiler;

use PhpArchitecture\Parser\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Grammar\Definition\Region;

class RegionInheritanceCompiler implements GrammarPrecompilerInterface, GrammarCompilerInterface
{
    private const INHERIT_FROM = 'inheritFrom';

    public function precompileGrammar(Grammar $grammar): void
    {
        $this->addInheritanceMetaRecursively($grammar->global, null);
    }

    private function addInheritanceMetaRecursively(Region $globalRegion, ?Region $parentRegion): void
    {
        /** @var array<string,int> $ancestors */
        $ancestors = $parentRegion?->getMeta(self::INHERIT_FROM, null) ?? [];

        foreach ($parentRegion?->regions ?? $globalRegion->regions as $regionName => $region) {
            $inheritance = [
                $globalRegion->name => $region->config->inheritanceFromGlobal
            ];

            foreach ($ancestors as $ancestorName => $ancestorScope) {
                if ($ancestorName === $globalRegion->name) {
                    continue;
                }

                $inheritance[$ancestorName] = $region->config->inheritanceFromAncestor & $ancestorScope;
            }

            if ($parentRegion !== null) {
                $inheritance[$parentRegion->name] = $region->config->inheritanceFromAncestor;
            }

            $region->setMeta(self::INHERIT_FROM, $inheritance);

            $this->addInheritanceMetaRecursively($globalRegion, $region);
        }
    }

    public function compileGrammar(Grammar $grammar): void
    {
        $allRegions = $grammar->getAllRegions();

        foreach ($allRegions as $region) {
            /** @var array<string,int> $inheritFrom */
            $inheritFrom = $region->getMeta(self::INHERIT_FROM, []);

            foreach ($inheritFrom as $sourceRegionName => $scope) {
                if ($scope === Region::NONE) {
                    continue;
                }

                $sourceRegion = $allRegions[$sourceRegionName] ?? null;
                if ($sourceRegion === null) {
                    continue;
                }

                $region->merge(
                    $sourceRegion,
                    $scope,
                    Region::MERGE_DEFAULT_MIDDLEWARES,
                    Region::MERGE_DEFAULT_OVERRIDE
                );
            }
        }
    }
}
