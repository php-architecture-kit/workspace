<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Compiled;

use PhpArchitecture\Parser\Grammar\Compiled\Model\CompiledGrammar;
use PhpArchitecture\Parser\Grammar\Compiled\Strategy\CompilerStrategyProvider;
use PhpArchitecture\Parser\Grammar\Definition\Grammar;

final class GrammarCompiler
{
    public function __construct(
        private readonly CompilerStrategyProvider $strategyProvider
    ) {}

    public function compile(Grammar $definition): CompiledGrammar
    {
        $workingRegions = $this->strategyProvider
            ->get(Strategy\ValidateAndFlattenStrategy::class)
            ->execute($definition);

        $workingRegions = $this->strategyProvider
            ->get(Strategy\DownTopPhaseStrategy::class)
            ->execute([$definition, $workingRegions]);

        $workingRegions = $this->strategyProvider
            ->get(Strategy\TopDownPhaseStrategy::class)
            ->execute([$definition, $workingRegions]);

        $workingRegions = $this->strategyProvider
            ->get(Strategy\ConvertClosuresToListenersStrategy::class)
            ->execute($workingRegions);

        return $this->strategyProvider
            ->get(Strategy\BuildCompiledModelsStrategy::class)
            ->execute([$definition, $workingRegions]);
    }
}
