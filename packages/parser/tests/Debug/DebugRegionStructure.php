<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Debug;

use PhpArchitecture\Parser\Grammar\Registry\Definition\Json\JsonRfc8259;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DebugRegionStructure extends TestCase
{
    #[Test]
    public function shouldShowRegionStructure(): void
    {
        $grammar = (new JsonRfc8259())->grammar();
        
        echo "\n=== GRAMMAR STRUCTURE BEFORE COMPILATION ===\n";
        echo "Global region rules:\n";
        foreach ($grammar->global->rules as $rule) {
            echo "  - {$rule->name}\n";
        }
        
        echo "\nGlobal region nested regions:\n";
        foreach ($grammar->global->regions as $regionName => $region) {
            echo "  - {$regionName}\n";
            echo "    Opener: " . ($region->config->opener ? "yes (rule: {$region->config->opener->onlyForRuleName})" : "no") . "\n";
            echo "    Closer: " . ($region->config->closer ? "yes" : "no") . "\n";
            echo "    Rules in this region:\n";
            foreach ($region->rules as $rule) {
                echo "      - {$rule->name}\n";
            }
        }
        
        $this->assertTrue(true);
    }
}
