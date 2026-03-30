<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Unit\Grammar\Compiled;

use PhpArchitecture\Parser\Grammar\Compiled\GrammarCompiler;
use PhpArchitecture\Parser\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Grammar\Definition\Region;
use PhpArchitecture\Parser\Grammar\Definition\Rule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class InheritanceTest extends TestCase
{
    #[Test]
    public function shouldInheritRulesFromGlobalRegion(): void
    {
        $compiler = new GrammarCompiler();
        
        $grammar = new Grammar('test', 'v1');
        $grammar->global->add(Rule::token('global_token', 'g'));
        
        $child = new Region('child');
        $child->setInheritanceFromGlobal(Region::RULES);
        $child->add(Rule::token('child_token', 'c'));
        
        $grammar->global->add($child);

        $compiled = $compiler->compile($grammar);

        $globalPatterns = count($compiled->regions['global']->patternLibrary->patterns);
        $childPatterns = count($compiled->regions['child']->patternLibrary->patterns);
        
        $this->assertSame(1, $globalPatterns, 'Global should have 1 pattern');
        $this->assertSame(2, $childPatterns, 'Child should have 2 patterns (global + own)');
    }
}
