<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Grammar\Definition;

use Closure;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Context\ContextStack;

/**
 * Holds rule default values.
 * 
 * style => Closure(ContextStack $parentContext, string $style):string
 */
class Defaults
{
    public const DEFAULT_STYLE = 'default';

    /** @var array<string,Closure(ContextStack $parentContext, string $style):string> */
    public private(set) array $factoryByStyles = [];

    /**
     * @param array<string,callable(ContextStack $parentContext, string $style):string> $factoryByStyles
     */
    public function __construct(
        array $factoryByStyles = [],
    ) {
        $this->factoryByStyles = array_map(
            static fn($factory) => $factory instanceof Closure ? $factory : Closure::fromCallable($factory),
            $factoryByStyles
        );
    }

    /**
     * @param callable(ContextStack $parentContext, string $style):string $factory
     */
    public function setFactoryForStyle(string $style, callable $factory): void
    {
        $this->factoryByStyles[$style] = $factory instanceof Closure ? $factory : Closure::fromCallable($factory);
    }
}
