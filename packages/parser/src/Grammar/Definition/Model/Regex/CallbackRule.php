<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Definition\Model\Regex;

use Closure;
use PhpArchitecture\Parser\Grammar\Definition\Model\RuleDefinition;
use PhpArchitecture\Parser\Grammar\Definition\Model\RuleType;
use PhpArchitecture\Parser\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Processing\Model\Tokenization\Token;
use InvalidArgumentException;

final class CallbackRule implements RuleDefinition
{
    public const LISTEN_IN_ALL_REGIONS = '*';
    public const GLOBAL_REGION = '__global__';
    public const PARENT_REGION = '__parent__';
    public const ROOT_REGION = '__root__';
    public const SAME_REGION = '__self__';

    /**
     * @param string[] $listenInRegions
     */
    public function __construct(
        public readonly Closure $callback,
        public readonly string $triggerRule,
        public readonly array $listenInRegions,
    ) {}

    public function toTokenRule(Rule $rule, Token $trigger): Rule
    {
        $regexRule = ($this->callback)($rule, $trigger);

        if (!$regexRule instanceof RegexRule) {
            throw new InvalidArgumentException('Callback must return RegexRule');
        }

        return new Rule(
            $rule->name,
            RuleType::Expression,
            $regexRule,
        );
    }
}
