<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tokenization;

use PhpArchitecture\Parser\Grammar\Definition\Regex\RegexRule;
use PhpArchitecture\Parser\Grammar\Grammar;
use PhpArchitecture\Parser\Grammar\Rule;
use PhpArchitecture\Parser\Tokenization\Model\Pattern;

class TokenizationContextCompiler
{
    public function compile(
        Grammar $grammar,
        int $chunkSize = 8192,
        int $safeMargin = 1024,
        bool $applyRowColTracking = true,
    ): Tokenization {
        $rootName = $grammar->rootRegion->name;
        $context = new Tokenization($rootName, $grammar->requireBofEof, $chunkSize, $safeMargin);

        return $context;
    }

    public function mapRuleToPattern(Rule $rule): Pattern
    {
        if (!$rule->definition instanceof RegexRule) {
            throw new \InvalidArgumentException('Rule definition must be a RegexRule');
        }

        return new Pattern($rule->name, $rule->definition->regex, $rule->priority);
    }

    private function setupPatternLibraries(Tokenization $context, Grammar $grammar): void
    {
        // TODO: Set up pattern library
    }

    private function setupEventDispatchers(Tokenization $context, Grammar $grammar): void
    {
        // TODO: Set up event dispatcher
    }

    private function applyRowColTracking(Tokenization $context): void
    {
        // TODO: Implement row/column tracking
    }
}
