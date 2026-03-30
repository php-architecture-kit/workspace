<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Debug;

use PhpArchitecture\Parser\Grammar\Compiled\GrammarCompiler;
use PhpArchitecture\Parser\Grammar\Registry\Definition\Json\JsonRfc8259;
use PhpArchitecture\Parser\Tokenization\Context\TokenizationContextCompiler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DebugJsonCompilation extends TestCase
{
    #[Test]
    public function shouldDebugCompiledGrammarAndContext(): void
    {
        $definitionGrammar = (new JsonRfc8259())->grammar();
        
        $grammarCompiler = new GrammarCompiler();
        $compiledGrammar = $grammarCompiler->compile($definitionGrammar);
        
        echo "\n=== COMPILED GRAMMAR DEBUG ===\n";
        echo "Grammar name: {$compiledGrammar->name}\n";
        echo "Grammar variant: {$compiledGrammar->variant}\n";
        echo "Require BOF/EOF: " . ($compiledGrammar->requireBofEof ? 'yes' : 'no') . "\n";
        echo "Total regions: " . count($compiledGrammar->regions) . "\n\n";
        
        foreach ($compiledGrammar->regions as $regionName => $region) {
            echo "Region: {$regionName}\n";
            echo "  Patterns: " . count($region->patternLibrary->patterns) . "\n";
            echo "  Sequences: " . count($region->sequenceLibrary->sequences) . "\n";
            echo "  Event subscribers: " . count($region->eventSubscribers) . "\n";
            
            if (count($region->patternLibrary->patterns) > 0) {
                echo "  Pattern names: " . implode(', ', array_keys($region->patternLibrary->patterns)) . "\n";
            }
            
            if (count($region->eventSubscribers) > 0) {
                echo "  Event types: ";
                $eventTypes = [];
                foreach ($region->eventSubscribers as $subscriber) {
                    $shortName = substr($subscriber->eventClassName, strrpos($subscriber->eventClassName, '\\') + 1);
                    $eventTypes[] = $shortName;
                }
                echo implode(', ', array_unique($eventTypes)) . "\n";
            }
            
            echo "\n";
        }
        
        $contextCompiler = new TokenizationContextCompiler();
        $context = $contextCompiler->compile($compiledGrammar, applyRowColTracking: false);
        
        echo "=== TOKENIZATION CONTEXT DEBUG ===\n";
        echo "Root name: {$context->rootName}\n";
        echo "Apply BOF/EOF: " . ($context->applyBofEof ? 'yes' : 'no') . "\n\n";
        
        $reflection = new \ReflectionClass($context);
        
        $patternLibraryMapProp = $reflection->getProperty('regionToPatternLibraryMap');
        $patternLibraryMap = $patternLibraryMapProp->getValue($context);
        
        echo "Pattern Library Map:\n";
        foreach ($patternLibraryMap as $regionName => $patternLibrary) {
            echo "  {$regionName}: " . count($patternLibrary->patterns) . " patterns\n";
            if (count($patternLibrary->patterns) > 0) {
                echo "    Names: " . implode(', ', array_keys($patternLibrary->patterns)) . "\n";
            }
        }
        echo "\n";
        
        $eventDispatcherMapProp = $reflection->getProperty('regionToEventDispatcherMap');
        $eventDispatcherMap = $eventDispatcherMapProp->getValue($context);
        
        echo "Event Dispatcher Map:\n";
        foreach ($eventDispatcherMap as $regionName => $dispatcher) {
            echo "  {$regionName}: has dispatcher\n";
        }
        echo "\n";
        
        $this->assertTrue(true);
    }
}
