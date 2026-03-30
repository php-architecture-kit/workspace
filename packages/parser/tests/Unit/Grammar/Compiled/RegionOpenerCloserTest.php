<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Unit\Grammar\Compiled;

use PhpArchitecture\Parser\Grammar\Compiled\GrammarCompiler;
use PhpArchitecture\Parser\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Grammar\Definition\Region;
use PhpArchitecture\Parser\Grammar\Definition\Rule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RegionOpenerCloserTest extends TestCase
{
    #[Test]
    public function shouldAddOpenerToParentAndCloserToRegionItself(): void
    {
        $compiler = new GrammarCompiler();
        
        $grammar = new Grammar('test', 'v1');
        
        $stringRegion = new Region('string');
        $stringRegion
            ->openWith(Rule::token('quote', '"'))
            ->closeWith(Rule::token('quote', '"'));
        
        $grammar->global->add($stringRegion);

        $compiled = $compiler->compile($grammar);

        $globalSubscribers = count($compiled->regions['global']->eventSubscribers);
        $stringSubscribers = count($compiled->regions['string']->eventSubscribers);
        
        $this->assertGreaterThan(0, $globalSubscribers, 'Global should have opener event subscriber');
        $this->assertGreaterThan(0, $stringSubscribers, 'String region should have closer event subscriber');
    }
}
