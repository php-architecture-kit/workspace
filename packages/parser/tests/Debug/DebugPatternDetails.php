<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Debug;

use PhpArchitecture\Parser\Grammar\Compiled\GrammarCompiler;
use PhpArchitecture\Parser\Grammar\Registry\Definition\Json\JsonRfc8259;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DebugPatternDetails extends TestCase
{
    #[Test]
    public function shouldShowPatternDetails(): void
    {
        $definitionGrammar = (new JsonRfc8259())->grammar();
        
        $grammarCompiler = new GrammarCompiler();
        $compiledGrammar = $grammarCompiler->compile($definitionGrammar);
        
        echo "\n=== PATTERN DETAILS FOR GLOBAL REGION ===\n";
        
        $globalRegion = $compiledGrammar->regions['global'];
        
        foreach ($globalRegion->patternLibrary->patterns as $name => $pattern) {
            echo "\nPattern: {$name}\n";
            echo "  Expression: {$pattern->expression}\n";
            echo "  Priority: {$pattern->priority}\n";
            echo "  Tags: " . implode(', ', $pattern->tags) . "\n";
        }
        
        echo "\n=== PATTERN DETAILS FOR STRING REGION ===\n";
        
        $stringRegion = $compiledGrammar->regions['string'];
        
        foreach ($stringRegion->patternLibrary->patterns as $name => $pattern) {
            echo "\nPattern: {$name}\n";
            echo "  Expression: {$pattern->expression}\n";
            echo "  Priority: {$pattern->priority}\n";
            echo "  Tags: " . implode(', ', $pattern->tags) . "\n";
        }
        
        echo "\n=== EVENT SUBSCRIBERS FOR GLOBAL REGION ===\n";
        
        foreach ($globalRegion->eventSubscribers as $hash => $subscriber) {
            $shortEventName = substr($subscriber->eventClassName, strrpos($subscriber->eventClassName, '\\') + 1);
            echo "\nEvent: {$shortEventName}\n";
            echo "  Priority: {$subscriber->priority}\n";
            echo "  Only for rule: " . ($subscriber->onlyForRuleName ?? 'all') . "\n";
            echo "  Listener type: " . get_class($subscriber->listener) . "\n";
        }
        
        $this->assertTrue(true);
    }
}
