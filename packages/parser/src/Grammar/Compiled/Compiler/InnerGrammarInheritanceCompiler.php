<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Compiled\Compiler;

use PhpArchitecture\Parser\Grammar\Compiled\GrammarCompiler;
use PhpArchitecture\Parser\Grammar\Definition\Region;

class InnerGrammarInheritanceCompiler implements RegionPrecompilerInterface
{
    public function __construct(
        private readonly GrammarCompiler $compiler,
    ) {}

    public function precompileRegion(Region $region): void
    {
        if ($region->config->retokenizeWithInnerGrammar !== false || $region->config->innerGrammar === null) {
            return;
        }

        $innerGrammar = $this->compiler->precompile($region->config->innerGrammar);
        $innerRootRegion = $innerGrammar->rootRegion;

        $region->merge(
            source: $innerRootRegion,
            scope: $region->config->innerGrammarMergeScope ?? Region::MERGE_DEFAULT_SCOPE,
            applyMiddlewares: $region->config->innerGrammarMergeMiddlewaresScope ?? Region::MERGE_DEFAULT_MIDDLEWARES,
            overrideSource: $region->config->innerGrammarMergeOverrideSource ?? Region::MERGE_DEFAULT_OVERRIDE,
        );
    }
}
