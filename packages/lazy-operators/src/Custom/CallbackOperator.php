<?php

declare(strict_types=1);

namespace PhpArchitecture\LazyOperators\Logical;

use Closure;
use PhpArchitecture\LazyOperators\Expression;

class CallbackOperator implements Expression
{
    /**
     * @var Expression[]
     */
    private readonly array $arguments;

    public function __construct(
        private readonly Closure $callback,
        Expression ...$arguments,
    ) {
        $this->arguments = $arguments;
    }

    public function __invoke(): mixed
    {
        return ($this->callback)(...array_map(static fn(Expression $expr) => $expr(), $this->arguments));
    }
}
