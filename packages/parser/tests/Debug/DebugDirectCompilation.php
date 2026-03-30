<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Debug;

use PhpArchitecture\Parser\Grammar\Compiled\Compiler\RuleToPatternCompiler;
use PhpArchitecture\Parser\Grammar\Definition\Rule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DebugDirectCompilation extends TestCase
{
    #[Test]
    public function shouldCompileRuleDirectly(): void
    {
        $rule = Rule::token('space', ' ', ['_ws']);
        
        echo "\n=== RULE DETAILS ===\n";
        echo "Name: {$rule->name}\n";
        echo "Type: {$rule->type->value}\n";
        echo "Definition class: " . get_class($rule->definition) . "\n";
        echo "Regex: {$rule->definition->regex}\n";
        echo "Priority: {$rule->priority}\n";
        echo "Tags: " . implode(', ', $rule->getAllTags()) . "\n";
        
        $compiler = new RuleToPatternCompiler();
        $pattern = $compiler->compileRule($rule);
        
        echo "\n=== PATTERN DETAILS ===\n";
        echo "Name: {$pattern->name}\n";
        echo "Pattern: {$pattern->pattern}\n";
        echo "Priority: {$pattern->priority}\n";
        echo "Tags: " . implode(', ', $pattern->tags) . "\n";
        
        $this->assertSame(' ', $pattern->pattern);
    }
}
