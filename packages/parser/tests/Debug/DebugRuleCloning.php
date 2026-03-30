<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Debug;

use PhpArchitecture\Parser\Grammar\Compiled\GrammarCompiler;
use PhpArchitecture\Parser\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Grammar\Definition\Rule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DebugRuleCloning extends TestCase
{
    #[Test]
    public function shouldShowRuleDetailsBeforeAndAfterCloning(): void
    {
        $grammar = new Grammar('test', 'v1');
        $grammar->global->add(
            Rule::token('space', ' '),
            Rule::expr('word', '[a-z]+')
        );
        
        echo "\n=== ORIGINAL GRAMMAR ===\n";
        foreach ($grammar->global->rules as $rule) {
            echo "Rule: {$rule->name}\n";
            echo "  Type: {$rule->type->value}\n";
            echo "  Definition class: " . get_class($rule->definition) . "\n";
            if (property_exists($rule->definition, 'regex')) {
                echo "  Regex: {$rule->definition->regex}\n";
            }
        }
        
        $compiler = new GrammarCompiler();
        $precompiled = $compiler->precompile($grammar);
        
        echo "\n=== PRECOMPILED GRAMMAR ===\n";
        foreach ($precompiled->global->rules as $rule) {
            echo "Rule: {$rule->name}\n";
            echo "  Type: {$rule->type->value}\n";
            echo "  Definition class: " . get_class($rule->definition) . "\n";
            if (property_exists($rule->definition, 'regex')) {
                echo "  Regex: {$rule->definition->regex}\n";
            }
        }
        
        $compiled = $compiler->compile($grammar);
        
        echo "\n=== COMPILED GRAMMAR ===\n";
        foreach ($compiled->regions['global']->patternLibrary->patterns as $name => $pattern) {
            echo "Pattern: {$name}\n";
            echo "  Expression: {$pattern->expression}\n";
        }
        
        $this->assertTrue(true);
    }
}
