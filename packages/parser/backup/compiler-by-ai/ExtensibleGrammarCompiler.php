<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Compiled;

use PhpArchitecture\Parser\Grammar\Compiled\Extension\CompilerExtensionInterface;
use PhpArchitecture\Parser\Grammar\Compiled\Model\CompiledGrammar;
use PhpArchitecture\Parser\Grammar\Compiled\Model\EventSubscriber as CompiledEventSubscriber;
use PhpArchitecture\Parser\Grammar\Compiled\Model\Region as CompiledRegion;
use PhpArchitecture\Parser\Grammar\Compiled\Model\Rule as CompiledRule;
use PhpArchitecture\Parser\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Grammar\Definition\Region;

/**
 * Extension-based Grammar Compiler.
 * 
 * Pipeline:
 * 1. Deep clone Definition\Grammar (mutable working copy)
 * 2. Apply extensions in priority order (modify grammar in-place)
 * 3. Flatten regions and build immutable CompiledGrammar
 */
final class ExtensibleGrammarCompiler
{
    /**
     * @param CompilerExtensionInterface[] $extensions
     */
    public function __construct(
        private readonly array $extensions = []
    ) {}

    public function compile(Grammar $grammar): CompiledGrammar
    {
        // Step 1: Deep clone grammar for mutation
        $workingGrammar = $this->deepCloneGrammar($grammar);

        // Step 2: Apply extensions in priority order
        $sortedExtensions = $this->sortExtensionsByPriority($this->extensions);
        foreach ($sortedExtensions as $extension) {
            $extension->apply($workingGrammar);
        }

        // Step 3: Flatten and build compiled grammar
        return $this->buildCompiledGrammar($workingGrammar);
    }

    private function deepCloneGrammar(Grammar $grammar): Grammar
    {
        $cloned = new Grammar($grammar->name, $grammar->variant);
        $cloned->requireBofEof = $grammar->requireBofEof;
        
        if (isset($grammar->rootRegion)) {
            $cloned->setRootRegion($this->cloneRegion($grammar->rootRegion, $grammar->getAllRegions()));
        }
        
        // Clone global region
        $this->copyRegionContents($grammar->global, $cloned->global);
        
        return $cloned;
    }

    /**
     * @param array<string, Region> $allRegions
     */
    private function cloneRegion(
        Region $source,
        array $allRegions
    ): Region {
        foreach ($allRegions as $region) {
            if ($region->name === $source->name) {
                return $region;
            }
        }
        return $source;
    }

    private function copyRegionContents(
        Region $source,
        Region $target
    ): void {
        // Copy rules
        foreach ($source->rules as $rule) {
            $target->add($rule);
        }
        
        // Copy event subscribers
        foreach ($source->eventSubscribers as $subscriber) {
            $target->add($subscriber);
        }
        
        // Copy nested regions recursively
        foreach ($source->regions as $childRegion) {
            $clonedChild = new Region(
                $childRegion->name,
                clone $childRegion->config
            );
            $this->copyRegionContents($childRegion, $clonedChild);
            $target->add($clonedChild);
        }
        
        // Copy metadata
        foreach ($source->getMetaAll() as $key => $value) {
            $target->setMeta($key, $value);
        }
        
        // Copy tags
        foreach ($source->getAllTags() as $tag) {
            $target->addTag($tag);
        }
    }

    /**
     * @param CompilerExtensionInterface[] $extensions
     * @return CompilerExtensionInterface[]
     */
    private function sortExtensionsByPriority(array $extensions): array
    {
        $sorted = $extensions;
        usort($sorted, fn($a, $b) => $a->priority() <=> $b->priority());
        return $sorted;
    }

    private function buildCompiledGrammar(Grammar $grammar): CompiledGrammar
    {
        $allRegions = $grammar->getAllRegions();
        $compiledRegions = [];
        $rootRegionName = $this->findRootRegionName($grammar, $allRegions);

        foreach ($allRegions as $regionName => $region) {
            $parentName = $this->findParentName($regionName, $allRegions);
            
            $compiledRules = [];
            foreach ($region->rules as $rule) {
                $compiledRules[] = new CompiledRule(
                    name: $rule->name,
                    type: $rule->type,
                    definition: $rule->definition,
                    tags: $rule->getAllTags(),
                    priority: $rule->priority,
                );
            }

            $compiledSubscribers = $this->convertEventSubscribers($region->eventSubscribers);

            $compiledRegions[$regionName] = new CompiledRegion(
                name: $regionName,
                rules: $compiledRules,
                eventSubscribers: $compiledSubscribers,
                parentRegionName: $parentName,
                metadata: [
                    'isRoot' => $regionName === $rootRegionName,
                    'tags' => $region->getAllTags(),
                ],
            );
        }

        return new CompiledGrammar(
            name: $grammar->name,
            variant: $grammar->variant,
            requireBofEof: $grammar->requireBofEof,
            regions: $compiledRegions,
        );
    }

    /**
     * @param \PhpArchitecture\Parser\Grammar\Definition\EventSubscriber[] $subscribers
     * @return CompiledEventSubscriber[]
     */
    private function convertEventSubscribers(array $subscribers): array
    {
        $compiled = [];
        
        foreach ($subscribers as $subscriber) {
            $compiled[] = new CompiledEventSubscriber(
                eventClassName: $subscriber->eventClassName,
                listener: $subscriber->listener,
                onlyForRuleName: $subscriber->onlyForRuleName,
                priority: $subscriber->priority,
            );
        }

        // Sort by priority descending
        usort($compiled, fn($a, $b) => $b->priority <=> $a->priority);
        
        return $compiled;
    }

    /**
     * @param array<string, Region> $allRegions
     */
    private function findRootRegionName(Grammar $grammar, array $allRegions): string
    {
        if (isset($grammar->rootRegion)) {
            return $grammar->rootRegion->name;
        }

        foreach ($allRegions as $region) {
            if ($this->findParentName($region->name, $allRegions) === null) {
                return $region->name;
            }
        }

        return 'global';
    }

    /**
     * @param array<string, Region> $allRegions
     */
    private function findParentName(string $regionName, array $allRegions): ?string
    {
        if ($regionName === 'global') {
            return null;
        }

        foreach ($allRegions as $name => $region) {
            if (isset($region->regions[$regionName])) {
                return $name;
            }
        }

        return null;
    }
}
