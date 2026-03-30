<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Functional\Grammar\Compiled;

use PhpArchitecture\Parser\Grammar\Compiled\Extension\DefaultExtensionProvider;
use PhpArchitecture\Parser\Grammar\Compiled\ExtensibleGrammarCompiler;
use PhpArchitecture\Parser\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Grammar\Definition\Model\Regex\CallbackRule;
use PhpArchitecture\Parser\Grammar\Definition\Region;
use PhpArchitecture\Parser\Grammar\Definition\Rule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DynamicTokenExtensionTest extends TestCase
{
    private ExtensibleGrammarCompiler $compiler;

    protected function setUp(): void
    {
        $this->compiler = new ExtensibleGrammarCompiler(
            DefaultExtensionProvider::getExtensions()
        );
    }

    #[Test]
    public function shouldAddDynamicTokenRuleToTargetRegion(): void
    {
        $grammar = new Grammar('test', 'dynamic');
        
        $targetRegion = new Region('target');
        $targetRegion->add(Rule::token('trigger', 'x'));
        
        $grammar->global->add(
            Rule::dynamic(
                name: 'dynamic_token',
                builder: fn($rule, $token) => RegexRule::fromString('dynamic'),
                triggerRule: 'trigger',
                listenInRegions: ['target']
            ),
            $targetRegion
        );

        $compiled = $this->compiler->compile($grammar);

        // Dynamic token rule should exist in target region
        $targetCompiledRegion = $compiled->regions['target'];
        $ruleNames = array_map(fn($r) => $r->name, $targetCompiledRegion->rules);
        
        $this->assertContains('dynamic_token', $ruleNames);
    }

    #[Test]
    public function shouldAddEventSubscriberToTargetRegion(): void
    {
        $grammar = new Grammar('test', 'dynamic');
        
        $targetRegion = new Region('target');
        $targetRegion->add(Rule::token('trigger', 'x'));
        
        $grammar->global->add(
            Rule::dynamic(
                name: 'dynamic_token',
                builder: fn($rule, $token) => RegexRule::fromString('dynamic'),
                triggerRule: 'trigger',
                listenInRegions: ['target']
            ),
            $targetRegion
        );

        $compiled = $this->compiler->compile($grammar);

        // EventSubscriber should be added to target region
        $targetCompiledRegion = $compiled->regions['target'];
        
        $this->assertGreaterThan(0, count($targetCompiledRegion->eventSubscribers));
    }

    #[Test]
    public function shouldAddDynamicTokenToMultipleRegions(): void
    {
        $grammar = new Grammar('test', 'dynamic');
        
        $region1 = new Region('region1');
        $region1->add(Rule::token('trigger', 'x'));
        
        $region2 = new Region('region2');
        $region2->add(Rule::token('trigger', 'x'));
        
        $grammar->global->add(
            Rule::dynamic(
                name: 'dynamic_token',
                triggerRule: 'trigger',
                builder: fn($match) => $match,
                listenInRegions: ['region1', 'region2']
            ),
            $region1,
            $region2
        );

        $compiled = $this->compiler->compile($grammar);

        // Dynamic token should exist in both regions
        $region1Rules = array_map(fn($r) => $r->name, $compiled->regions['region1']->rules);
        $region2Rules = array_map(fn($r) => $r->name, $compiled->regions['region2']->rules);
        
        $this->assertContains('dynamic_token', $region1Rules);
        $this->assertContains('dynamic_token', $region2Rules);
    }

    #[Test]
    public function shouldHandleGlobalRegionAsTarget(): void
    {
        $grammar = new Grammar('test', 'dynamic');
        
        $grammar->global->add(
            Rule::token('trigger', 'x'),
            Rule::dynamic(
                name: 'dynamic_token',
                triggerRule: 'trigger',
                builder: fn($match) => $match,
                listenInRegions: [CallbackRule::GLOBAL_REGION]
            )
        );

        $compiled = $this->compiler->compile($grammar);

        // Dynamic token should exist in global region
        $globalRules = array_map(fn($r) => $r->name, $compiled->regions['global']->rules);
        
        $this->assertContains('dynamic_token', $globalRules);
        $this->assertGreaterThan(0, count($compiled->regions['global']->eventSubscribers));
    }

    #[Test]
    public function shouldNotDuplicateDynamicTokenInSameRegion(): void
    {
        $grammar = new Grammar('test', 'dynamic');
        
        $targetRegion = new Region('target');
        $targetRegion->add(Rule::token('trigger', 'x'));
        
        $grammar->global->add(
            Rule::dynamic(
                name: 'dynamic_token',
                triggerRule: 'trigger',
                builder: fn($match) => $match,
                listenInRegions: ['target', 'target']
            ),
            $targetRegion
        );

        $compiled = $this->compiler->compile($grammar);

        // Should only have one dynamic_token rule
        $targetRules = array_filter(
            $compiled->regions['target']->rules,
            fn($r) => $r->name === 'dynamic_token'
        );
        
        $this->assertCount(1, $targetRules);
    }
}
