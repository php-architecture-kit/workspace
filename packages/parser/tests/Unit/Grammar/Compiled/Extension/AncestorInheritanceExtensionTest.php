<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Unit\Grammar\Compiled\Extension;

use PhpArchitecture\Parser\Grammar\Compiled\Extension\AncestorInheritanceExtension;
use PhpArchitecture\Parser\Grammar\Definition\EventSubscriber;
use PhpArchitecture\Parser\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Grammar\Definition\Region;
use PhpArchitecture\Parser\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Processing\Event\Tokenization\TokenAddedEvent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AncestorInheritanceExtensionTest extends TestCase
{
    private AncestorInheritanceExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new AncestorInheritanceExtension();
    }

    #[Test]
    public function shouldInheritRulesFromParent(): void
    {
        $grammar = new Grammar('test', 'variant');
        
        $parentRegion = new Region('parent');
        $parentRegion->add(Rule::token('parent_rule', 'x'));
        
        $childRegion = new Region('child');
        $childRegion->add(Rule::token('child_rule', 'y'));
        
        $parentRegion->add($childRegion);
        $grammar->global->add($parentRegion);

        $this->extension->apply($grammar);

        $child = $grammar->getAllRegions()['child'];
        $this->assertCount(2, $child->rules);
        $this->assertArrayHasKey('parent_rule', $child->rules);
        $this->assertArrayHasKey('child_rule', $child->rules);
    }

    #[Test]
    public function shouldInheritEventSubscribersFromParent(): void
    {
        $grammar = new Grammar('test', 'variant');
        
        $parentRegion = new Region('parent');
        $parentRegion->add(
            EventSubscriber::on(TokenAddedEvent::class, static fn() => 'parent')
        );
        
        $childRegion = new Region('child');
        $parentRegion->add($childRegion);
        $grammar->global->add($parentRegion);

        $this->extension->apply($grammar);

        $child = $grammar->getAllRegions()['child'];
        $this->assertCount(1, $child->eventSubscribers);
    }

    #[Test]
    public function shouldNotOverrideChildRules(): void
    {
        $grammar = new Grammar('test', 'variant');
        
        $grammar->global->add(Rule::token('same_name', 'parent'));
        
        $childRegion = new Region('child');
        $childRegion->add(Rule::token('same_name', 'child'));
        
        $grammar->global->add($childRegion);

        $childRule = $childRegion->rules['same_name'];
        
        $this->extension->apply($grammar);

        $child = $grammar->getAllRegions()['child'];
        $this->assertSame($childRule, $child->rules['same_name']);
    }

    #[Test]
    public function shouldRespectExcludeInheritanceFlag(): void
    {
        $grammar = new Grammar('test', 'variant');
        
        $grammar->global->add(Rule::token('parent_rule', 'x'));
        
        $childRegion = new Region('child');
        $childRegion->includeAncestorRules(false);
        $childRegion->add(Rule::token('child_rule', 'y'));
        
        $grammar->global->add($childRegion);

        $this->extension->apply($grammar);

        $child = $grammar->getAllRegions()['child'];
        $this->assertCount(1, $child->rules);
        $this->assertArrayNotHasKey('parent_rule', $child->rules);
    }

    #[Test]
    public function shouldHaveCorrectPriority(): void
    {
        $this->assertSame(500, $this->extension->priority());
    }
}
