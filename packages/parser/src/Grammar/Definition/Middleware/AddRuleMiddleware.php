<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Definition\Middleware;

use PhpArchitecture\Parser\Grammar\Definition\Rule;

class AddRuleMiddleware extends AbstractMiddleware
{
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
}
