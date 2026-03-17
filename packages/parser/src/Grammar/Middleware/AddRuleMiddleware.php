<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Middleware;

use Closure;
use PhpArchitecture\Parser\Grammar\Rule;

final class AddRuleMiddleware implements GrammarMiddleware
{
    public function __construct(
        private Closure $callback,
        private int $priority = 0,
    ) {}

    /**
     * @param Rule $rule
     * @return Rule
     */
    public function handle(object $rule): object
    {
        return ($this->callback)($rule);
    }

    public function method(): string
    {
        return self::ADD_RULE;
    }

    public function priority(): int
    {
        return $this->priority;
    }
}
