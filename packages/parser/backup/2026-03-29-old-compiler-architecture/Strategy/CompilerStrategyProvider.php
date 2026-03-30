<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Compiled\Strategy;

final class CompilerStrategyProvider 
{
    /** @var array<class-string<CompilerStrategyInterface>, CompilerStrategyInterface> */
    private array $strategies = [];

    /**
     * @template T of CompilerStrategyInterface
     * @param class-string<T> $strategyClass
     * @return T
     */
    public function get(string $strategyClass): CompilerStrategyInterface
    {
        if (!isset($this->strategies[$strategyClass])) {
            $this->strategies[$strategyClass] = new $strategyClass();
        }

        return $this->strategies[$strategyClass];
    }
}
