<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Definition\Regex;

use Closure;
use PhpArchitecture\Parser\Grammar\Definition\RuleDefinition;
use PhpArchitecture\Parser\Grammar\Definition\RuleType;
use PhpArchitecture\Parser\Grammar\Rule;
use PhpArchitecture\Parser\Tokenization\Model\Token;

final class CallbackRule implements RuleDefinition
{
    public function __construct(
        public readonly Closure $callback,
    ) {}

    public function toTokenRule(Rule $rule, Token $trigger): Rule
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
