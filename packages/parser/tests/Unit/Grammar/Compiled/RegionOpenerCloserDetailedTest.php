<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Unit\Grammar\Compiled;

use PhpArchitecture\Parser\Grammar\Compiled\GrammarCompiler;
use PhpArchitecture\Parser\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Grammar\Definition\Region;
use PhpArchitecture\Parser\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Processing\Event\Tokenization\TokenAddedEvent;
use PhpArchitecture\Parser\Processing\Event\Tokenization\TokenMatchedEvent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RegionOpenerCloserDetailedTest extends TestCase
{
    #[Test]
    public function shouldAddOpenerEventSubscriberToParentRegion(): void
    {
        $compiler = new GrammarCompiler();
        
        $grammar = new Grammar('test', 'v1');
        
        $stringRegion = new Region('string');
        $stringRegion->openWith(Rule::token('quote', '"'));
        
        $grammar->global->add($stringRegion);

        $compiled = $compiler->compile($grammar);

        $globalSubscribers = $compiled->regions['global']->eventSubscribers;
        
        $this->assertGreaterThan(0, count($globalSubscribers), 'Global should have opener event subscriber');
        
        $hasTokenMatchedEvent = false;
        foreach ($globalSubscribers as $subscriber) {
            if ($subscriber->eventClassName === TokenMatchedEvent::class) {
                $hasTokenMatchedEvent = true;
                break;
            }
        }
        
        $this->assertTrue($hasTokenMatchedEvent, 'Global should have TokenMatchedEvent for opening string region');
    }

    #[Test]
    public function shouldAddCloserEventSubscriberToRegionItself(): void
    {
        $compiler = new GrammarCompiler();
        
        $grammar = new Grammar('test', 'v1');
        
        $stringRegion = new Region('string');
        $stringRegion->closeWith(Rule::token('quote', '"'));
        
        $grammar->global->add($stringRegion);

        $compiled = $compiler->compile($grammar);

        $stringSubscribers = $compiled->regions['string']->eventSubscribers;
        
        $this->assertGreaterThan(0, count($stringSubscribers), 'String region should have closer event subscriber');
        
        $hasTokenAddedEvent = false;
        foreach ($stringSubscribers as $subscriber) {
            if ($subscriber->eventClassName === TokenAddedEvent::class) {
                $hasTokenAddedEvent = true;
                break;
            }
        }
        
        $this->assertTrue($hasTokenAddedEvent, 'String region should have TokenAddedEvent for closing');
    }

    #[Test]
    public function shouldAddBothOpenerAndCloserForCompleteRegion(): void
    {
        $compiler = new GrammarCompiler();
        
        $grammar = new Grammar('test', 'v1');
        
        $stringRegion = new Region('string');
        $stringRegion
            ->openWith(Rule::token('open_quote', '"'))
            ->closeWith(Rule::token('close_quote', '"'));
        
        $grammar->global->add($stringRegion);

        $compiled = $compiler->compile($grammar);

        $globalSubscribers = count($compiled->regions['global']->eventSubscribers);
        $stringSubscribers = count($compiled->regions['string']->eventSubscribers);
        
        $this->assertGreaterThan(0, $globalSubscribers, 'Global should have opener');
        $this->assertGreaterThan(0, $stringSubscribers, 'String should have closer');
    }

    #[Test]
    public function shouldAddOpenerToCorrectParentInNestedStructure(): void
    {
        $compiler = new GrammarCompiler();
        
        $grammar = new Grammar('test', 'v1');
        
        $parentRegion = new Region('parent');
        $childRegion = new Region('child');
        
        $childRegion->openWith(Rule::token('open', '{'));
        
        $parentRegion->add($childRegion);
        $grammar->global->add($parentRegion);

        $compiled = $compiler->compile($grammar);

        $parentSubscribers = count($compiled->regions['parent']->eventSubscribers);
        $globalSubscribers = count($compiled->regions['global']->eventSubscribers);
        
        $this->assertGreaterThan(0, $parentSubscribers, 'Parent should have opener for child');
        $this->assertSame(0, $globalSubscribers, 'Global should not have opener for child (parent is direct parent)');
    }

    #[Test]
    public function shouldNotAddSubscribersWhenOpenerAndCloserAreNull(): void
    {
        $compiler = new GrammarCompiler();
        
        $grammar = new Grammar('test', 'v1');
        
        $simpleRegion = new Region('simple');
        $simpleRegion->add(Rule::token('token', 'x'));
        
        $grammar->global->add($simpleRegion);

        $compiled = $compiler->compile($grammar);

        $simpleSubscribers = count($compiled->regions['simple']->eventSubscribers);
        
        $this->assertSame(0, $simpleSubscribers, 'Simple region should not have opener/closer subscribers');
    }
}
