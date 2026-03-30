<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Debug;

use PhpArchitecture\Parser\Grammar\Compiled\GrammarCompiler;
use PhpArchitecture\Parser\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Tokenization\Context\TokenizationContextCompiler;
use PhpArchitecture\Parser\Tokenization\Tokenizer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MinimalTokenizationTest extends TestCase
{
    #[Test]
    public function shouldTokenizeSimpleInput(): void
    {
        $grammar = new Grammar('test', 'v1');
        $grammar->global->add(
            Rule::token('space', ' '),
            Rule::keyword('null')
        );
        $grammar->setRootRegion($grammar->global);
        
        $grammarCompiler = new GrammarCompiler();
        $compiledGrammar = $grammarCompiler->compile($grammar);
        
        echo "\n=== COMPILED PATTERNS ===\n";
        foreach ($compiledGrammar->regions['global']->patternLibrary->patterns as $name => $pattern) {
            echo "{$name}: {$pattern->pattern}\n";
        }
        
        $contextCompiler = new TokenizationContextCompiler();
        $context = $contextCompiler->compile($compiledGrammar, applyRowColTracking: false);
        
        $tokenizer = new Tokenizer();
        $tokens = $tokenizer->tokenize('null null', $context);
        
        echo "\n=== TOKENS ===\n";
        foreach ($tokens as $token) {
            echo "{$token->name}: '{$token->value}'\n";
        }
        
        $this->assertGreaterThan(2, count($tokens));
    }
}
