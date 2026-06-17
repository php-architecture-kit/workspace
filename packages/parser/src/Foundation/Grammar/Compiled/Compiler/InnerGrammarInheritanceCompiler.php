<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Grammar\Compiled\Compiler;

use PhpArchitecture\Parser\Foundation\Grammar\Compiled\GrammarCompiler;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Region;

class InnerGrammarInheritanceCompiler implements RegionPrecompilerInterface
{
    public function __construct(
        private readonly GrammarCompiler $compiler,
        private readonly RegionConflictResolver $conflictResolver = new RegionConflictResolver(),
    ) {}

    public function precompileRegion(Region $region, Grammar $grammar): void
    {
        if ($region->config->innerGrammar === null) {
            return;
        }

        if ($region->config->retokenizeWithInnerGrammar === true) {
            # The inner grammar is a genuinely separate grammar (its own tokenization is already
            # handled by RetokenizeRegionEventListener using "innerGrammar.compiled" below). It must
            # NOT be merged into this region's definition here — that would let the host grammar's own
            # compilers (RegionOpenerCloserCompiler, TagToChoiceCompiler, RuleRefResolutionCompiler, ...)
            # incorrectly process content that belongs to a different grammar.
            #
            # Only the inner grammar's matching-relevant content (Sequence rules, regions, non-tokenization
            # event listeners) needs to become reachable for the *matching* stage. That can only happen once
            # both grammars are fully, independently compiled, so it's done as a post-compile splice in
            # GrammarCompiler::mergeRetokenizedInnerGrammars() — comparing the already-compiled CompiledRegions
            # of both sides directly (not their Region definitions), since only the fully compiled artifacts
            # are guaranteed to be at the same pipeline stage on both sides (e.g. a region synthesized by
            # RegionOpenerCloserCompiler from a tagged-rule region opener only exists post-compile).
            $region->setMeta("innerGrammar.compiled", $this->compiler->compile($region->config->innerGrammar));

            return;
        }

        $innerGrammar    = $this->compiler->precompile($region->config->innerGrammar);
        $innerRootRegion = $innerGrammar->rootRegion;
        $existingRegions = $grammar->getAllRegions();

        $excludeRegionNames = $this->conflictResolver->resolveExclusions(
            $innerRootRegion->regions,
            $existingRegions,
            "region '{$region->name}'",
        );

        $region->merge(
            source: $innerRootRegion,
            scope: $region->config->innerGrammarMergeScope ?? Region::MERGE_DEFAULT_SCOPE,
            applyMiddlewares: $region->config->innerGrammarMergeMiddlewaresScope ?? Region::MERGE_DEFAULT_MIDDLEWARES,
            overrideSource: $region->config->innerGrammarMergeOverrideSource ?? Region::MERGE_DEFAULT_OVERRIDE,
            excludeRegionNames: $excludeRegionNames,
        );
    }
}
