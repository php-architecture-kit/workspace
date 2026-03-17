<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Middleware;

use Closure;

final class AddMiddlewareMiddleware implements GrammarMiddleware
{
    public function __construct(
        private Closure $callback,
        private int $priority = 0,
    ) {}

    /**
     * @param GrammarMiddleware $middleware
     * @return GrammarMiddleware
     */
    public function handle(object $middleware): object
    {
        return ($this->callback)($middleware);
    }

    public function method(): string
    {
        return self::ADD_MIDDLEWARE;
    }

    public function priority(): int
    {
        return $this->priority;
    }
}
