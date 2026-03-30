<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Debug;

use PhpArchitecture\Parser\Grammar\Compiled\GrammarCompiler;
use PhpArchitecture\Parser\Grammar\Registry\Definition\Json\JsonRfc8259;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DebugPatternDetailsFixed extends TestCase
{
    #[Test]
    public function shouldShowCorrectPatternDetails(): void
    {
        $definitionGrammar = (new JsonRfc8259())->grammar();
        
        $grammarCompiler = new GrammarCompiler();
        $compiledGrammar = $grammarCompiler->compile($definitionGrammar);
        
        echo "\n=== PATTERN DETAILS FOR GLOBAL REGION (FIXED) ===\n";
        
        $globalRegion = $compiledGrammar->regions['global'];
        
        foreach ($globalRegion->patternLibrary->patterns as $name => $pattern) {
            echo "\nPattern: {$name}\n";
            echo "  Pattern (regex): {$pattern->pattern}\n";
            echo "  Priority: {$pattern->priority}\n";
            echo "  Tags: " . implode(', ', $pattern->tags) . "\n";
        }
        
        $this->assertTrue(true);
    }
}
