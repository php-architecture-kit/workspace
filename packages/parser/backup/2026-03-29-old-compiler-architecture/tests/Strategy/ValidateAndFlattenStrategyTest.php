<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Unit\Grammar\Compiled\Strategy;

use PhpArchitecture\Parser\Grammar\Compiled\Strategy\ValidateAndFlattenStrategy;
use PhpArchitecture\Parser\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Grammar\Definition\Region;
use PhpArchitecture\Parser\Grammar\Definition\Rule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ValidateAndFlattenStrategyTest extends TestCase
{
    #[Test]
    public function flattensSimpleGrammarWithGlobalRegionOnly(): void
    {
        $grammar = new Grammar('test');
        
        $strategy = new ValidateAndFlattenStrategy();
        $result = $strategy->execute($grammar);
        
        $this->assertCount(1, $result);
        $this->assertArrayHasKey('global', $result);
        $this->assertSame('global', $result['global']->source->name);
        $this->assertNull($result['global']->parentName);
    }

    #[Test]
    public function flattensGrammarWithNestedRegions(): void
    {
        $grammar = new Grammar('test');
        
        $childRegion = new Region('child');
        $grammar->global->add($childRegion);
        
        $strategy = new ValidateAndFlattenStrategy();
        $result = $strategy->execute($grammar);
        
        $this->assertCount(2, $result);
        $this->assertArrayHasKey('global', $result);
        $this->assertArrayHasKey('child', $result);
        $this->assertNull($result['global']->parentName);
        $this->assertSame('global', $result['child']->parentName);
    }

    #[Test]
    public function flattensGrammarWithDeeplyNestedRegions(): void
    {
        $grammar = new Grammar('test');
        
        $level1 = new Region('level1');
        $level2 = new Region('level2');
        $level3 = new Region('level3');
        
        $grammar->global->add($level1);
        $level1->add($level2);
        $level2->add($level3);
        
        $strategy = new ValidateAndFlattenStrategy();
        $result = $strategy->execute($grammar);
        
        $this->assertCount(4, $result);
        $this->assertArrayHasKey('global', $result);
        $this->assertArrayHasKey('level1', $result);
        $this->assertArrayHasKey('level2', $result);
        $this->assertArrayHasKey('level3', $result);
        
        $this->assertNull($result['global']->parentName);
        $this->assertSame('global', $result['level1']->parentName);
        $this->assertSame('level1', $result['level2']->parentName);
        $this->assertSame('level2', $result['level3']->parentName);
    }

    #[Test]
    public function preservesRulesFromSourceRegion(): void
    {
        $grammar = new Grammar('test');
        
        $rule1 = Rule::token('token1', 'test');
        $rule2 = Rule::expr('expr1', '[a-z]+');
        $grammar->global->add($rule1, $rule2);
        
        $strategy = new ValidateAndFlattenStrategy();
        $result = $strategy->execute($grammar);
        
        $this->assertCount(2, $result['global']->rules);
        $this->assertArrayHasKey('token1', $result['global']->rules);
        $this->assertArrayHasKey('expr1', $result['global']->rules);
    }

    #[Test]
    public function preservesEventSubscribersFromSourceRegion(): void
    {
        $grammar = new Grammar('test');
        
        $subscriber = \PhpArchitecture\Parser\Grammar\Definition\EventSubscriber::on(
            \PhpArchitecture\Parser\Processing\Event\Tokenization\TokenAddedEvent::class,
            fn($event, $context) => null
        );
        $grammar->global->add($subscriber);
        
        $strategy = new ValidateAndFlattenStrategy();
        $result = $strategy->execute($grammar);
        
        $this->assertCount(1, $result['global']->eventSubscribers);
    }

    #[Test]
    public function handlesMultipleSiblingsCorrectly(): void
    {
        $grammar = new Grammar('test');
        
        $sibling1 = new Region('sibling1');
        $sibling2 = new Region('sibling2');
        $sibling3 = new Region('sibling3');
        
        $grammar->global->add($sibling1, $sibling2, $sibling3);
        
        $strategy = new ValidateAndFlattenStrategy();
        $result = $strategy->execute($grammar);
        
        $this->assertCount(4, $result);
        $this->assertSame('global', $result['sibling1']->parentName);
        $this->assertSame('global', $result['sibling2']->parentName);
        $this->assertSame('global', $result['sibling3']->parentName);
    }
}
