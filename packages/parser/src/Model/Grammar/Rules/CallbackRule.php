<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Model\Grammar\Rules;

use Closure;
use PhpArchitecture\Parser\Model\Grammar\Rule;
use PhpArchitecture\Parser\Model\Grammar\RuleType;
use PhpArchitecture\Parser\Model\Token\TokenInterface;

final class CallbackRule implements RuleDefinition
{
    public function __construct(
        public readonly Closure $callback,
    ) {}

    public function toTokenRule(Rule $rule, TokenInterface $trigger): Rule
    {
        $regexRule = ($this->callback)($rule, $trigger);

        if (!$regexRule instanceof RegexRule) {
            throw new \InvalidArgumentException('Callback must return RegexRule');
        }

        return new Rule(
            $rule->name,
            RuleType::Expression,
            $regexRule
        );
    }
}
