<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Grammar\Definition\Middleware;

class AddMiddlewareMiddleware extends AbstractMiddleware
{
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
}
