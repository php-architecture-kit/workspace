<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Functional\Grammar\Compiled;

use PhpArchitecture\Parser\Grammar\Compiled\Extension\DefaultExtensionProvider;
use PhpArchitecture\Parser\Grammar\Compiled\ExtensibleGrammarCompiler;
use PhpArchitecture\Parser\Grammar\Definition\EventSubscriber;
use PhpArchitecture\Parser\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Grammar\Definition\Region;
use PhpArchitecture\Parser\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Processing\Event\Tokenization\TokenAddedEvent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class InheritanceExtensionsTest extends TestCase
{
    private ExtensibleGrammarCompiler $compiler;

    protected function setUp(): void
    {
        $this->compiler = new ExtensibleGrammarCompiler(
            DefaultExtensionProvider::getExtensions()
        );
    }

    #[Test]
    public function childRegionShouldInheritRulesFromParent(): void
    {
        $grammar = new Grammar('test', 'inheritance');
        
        $grammar->global->add(
            Rule::token('global_rule', 'x')
        );
        
        $childRegion = new Region('child');
        $childRegion->add(Rule::token('child_rule', 'y'));
        
        $grammar->global->add($childRegion);

        $compiled = $this->compiler->compile($grammar);

        $childRuleNames = array_map(fn($r) => $r->name, $compiled->regions['child']->rules);
        
        $this->assertContains('global_rule', $childRuleNames, 
            'Child region should inherit rules from parent');
        $this->assertContains('child_rule', $childRuleNames,
            'Child region should keep its own rules');
    }

    #[Test]
    public function childRegionShouldInheritEventSubscribersFromParent(): void
    {
        $grammar = new Grammar('test', 'inheritance');
        
        $grammar->global->add(
            EventSubscriber::on(TokenAddedEvent::class, fn() => 'parent')
        );
        
        $childRegion = new Region('child');
        $childRegion->add(Rule::token('child_rule', 'y'));
        
        $grammar->global->add($childRegion);

        $compiled = $this->compiler->compile($grammar);

        $this->assertGreaterThan(0, count($compiled->regions['child']->eventSubscribers),
            'Child region should inherit event subscribers from parent');
    }

    #[Test]
    public function childRuleShouldNotBeOverriddenByParentRule(): void
    {
        $grammar = new Grammar('test', 'inheritance');
        
        $grammar->global->add(
            Rule::token('same_name', 'parent')->priority(10)
        );
        
        $childRegion = new Region('child');
        $childRegion->add(
            Rule::token('same_name', 'child')->priority(20)
        );
        
        $grammar->global->add($childRegion);

        $compiled = $this->compiler->compile($grammar);

        $childRule = null;
        foreach ($compiled->regions['child']->rules as $rule) {
            if ($rule->name === 'same_name') {
                $childRule = $rule;
                break;
            }
        }

        $this->assertNotNull($childRule);
        $this->assertSame(20, $childRule->priority,
            'Child rule should not be overridden by parent rule with same name');
    }

    #[Test]
    public function shouldRespectExcludeInheritanceFlag(): void
    {
        $grammar = new Grammar('test', 'inheritance');
        
        $grammar->global->add(
            Rule::token('parent_rule', 'x')
        );
        
        $childRegion = new Region('child');
        $childRegion->excludeInheritance(ancestor: true, global: true);
        $childRegion->add(Rule::token('child_rule', 'y'));
        
        $grammar->global->add($childRegion);

        $compiled = $this->compiler->compile($grammar);

        $childRuleNames = array_map(fn($r) => $r->name, $compiled->regions['child']->rules);
        
        $this->assertNotContains('parent_rule', $childRuleNames,
            'Child region should not inherit rules when excludeInheritance is set');
        $this->assertContains('child_rule', $childRuleNames);
    }

    #[Test]
    public function allRegionsShouldInheritFromGlobal(): void
    {
        $grammar = new Grammar('test', 'inheritance');
        
        $grammar->global->add(
            Rule::token('global_rule', 'g')
        );
        
        $region1 = new Region('region1');
        $region1->add(Rule::token('rule1', 'x'));
        
        $region2 = new Region('region2');
        $region2->add(Rule::token('rule2', 'y'));
        
        $grammar->global->add($region1, $region2);

        $compiled = $this->compiler->compile($grammar);

        $region1Rules = array_map(fn($r) => $r->name, $compiled->regions['region1']->rules);
        $region2Rules = array_map(fn($r) => $r->name, $compiled->regions['region2']->rules);
        
        $this->assertContains('global_rule', $region1Rules,
            'Region1 should inherit from global');
        $this->assertContains('global_rule', $region2Rules,
            'Region2 should inherit from global');
    }

    #[Test]
    public function shouldRespectExcludeGlobalRulesFlag(): void
    {
        $grammar = new Grammar('test', 'inheritance');
        
        $grammar->global->add(
            Rule::token('global_rule', 'g')
        );
        
        $childRegion = new Region('child');
        $childRegion->excludeInheritance(ancestor: false, global: true);
        $childRegion->add(Rule::token('child_rule', 'y'));
        
        $grammar->global->add($childRegion);

        $compiled = $this->compiler->compile($grammar);

        $childRuleNames = array_map(fn($r) => $r->name, $compiled->regions['child']->rules);
        
        $this->assertNotContains('global_rule', $childRuleNames,
            'Child region should not inherit from global when excludeInheritance(global: true) is set');
    }

    #[Test]
    public function nestedRegionsShouldInheritFromAllAncestors(): void
    {
        $grammar = new Grammar('test', 'inheritance');
        
        $grammar->global->add(
            Rule::token('global_rule', 'g')
        );
        
        $parentRegion = new Region('parent');
        $parentRegion->add(Rule::token('parent_rule', 'p'));
        
        $childRegion = new Region('child');
        $childRegion->add(Rule::token('child_rule', 'c'));
        
        $parentRegion->add($childRegion);
        $grammar->global->add($parentRegion);

        $compiled = $this->compiler->compile($grammar);

        $childRuleNames = array_map(fn($r) => $r->name, $compiled->regions['child']->rules);
        
        $this->assertContains('global_rule', $childRuleNames,
            'Nested child should inherit from global');
        $this->assertContains('parent_rule', $childRuleNames,
            'Nested child should inherit from parent');
        $this->assertContains('child_rule', $childRuleNames,
            'Nested child should have its own rules');
    }
}
