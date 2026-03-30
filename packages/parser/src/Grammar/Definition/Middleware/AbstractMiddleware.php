<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Definition\Middleware;

use Closure;
use PhpArchitecture\Parser\Shared\Hash\HashClosure;

abstract class AbstractMiddleware implements GrammarMiddleware
{
    use HashClosure;

    public function __construct(
        protected Closure $callback,
        protected int $priority = 0,
    ) {}

    public function hash(): string
    {
        return hash('xxh128', implode('|', [
            static::class,
            $this->method(),
            $this->hashClosure($this->callback),
            (string) $this->priority,
        ]));
    }

    public function handle(object $rule): object
    {
        return ($this->callback)($rule);
    }

    public function priority(): int
    {
        return $this->priority;
    }
}
