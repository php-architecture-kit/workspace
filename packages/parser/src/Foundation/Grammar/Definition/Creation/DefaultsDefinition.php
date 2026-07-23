<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Grammar\Definition\Creation;

use Closure;
use PhpArchitecture\Parser\Foundation\ParsedTree\Context\ContextStack;

class DefaultsDefinition
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
            $factoryByStyles,
        );
    }

    /**
     * @param callable(ContextStack $parentContext, string $style):string $factory
     */
    public function setFactoryForStyle(string $style, callable $factory): void
    {
        $this->factoryByStyles[$style] = $factory instanceof Closure ? $factory : Closure::fromCallable($factory);
    }

    /**
     * Fluent variant of setFactoryForStyle().
     *
     * @param callable(ContextStack $parentContext, string $style):string $factory
     */
    public function forStyle(string $style, callable $factory): self
    {
        $this->setFactoryForStyle($style, $factory);

        return $this;
    }

    /**
     * Returns the factory for $style, falling back to the DEFAULT_STYLE factory,
     * or null when neither is registered.
     *
     * @return Closure(ContextStack $parentContext, string $style):string|null
     */
    public function factoryFor(string $style): ?Closure
    {
        return $this->factoryByStyles[$style]
            ?? $this->factoryByStyles[self::DEFAULT_STYLE]
            ?? null;
    }
}
