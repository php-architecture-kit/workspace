<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Compiled\Compiler;

use LogicException;
use PhpArchitecture\Parser\Grammar\Definition\Model\Regex\RegexRule;
use PhpArchitecture\Parser\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Processing\Model\Tokenization\Pattern;

class RuleToPatternCompiler implements RuleCompilerInterface
{
    public function supports(object $object): bool
    {
        return $object instanceof Rule && $object->definition instanceof RegexRule;
    }

    public function compileRule(Rule $rule): Pattern
    {
        if (!$this->supports($rule)) {
            throw new LogicException("Unsupported rule type. Rule must be a tokenization rule.");
        }

        $regexRule = $rule->definition;
        if (!$regexRule instanceof RegexRule) {
            throw new LogicException("Unsupported definition type. Compiler require RegexRule definition.");
        }

        return new Pattern(
            $rule->name,
            $regexRule->regex,
            $rule->priority,
            $rule->getAllTags(),
        );
    }
}
