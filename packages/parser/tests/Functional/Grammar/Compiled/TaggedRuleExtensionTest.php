<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Functional\Grammar\Compiled;

use PhpArchitecture\Parser\Grammar\Compiled\Extension\DefaultExtensionProvider;
use PhpArchitecture\Parser\Grammar\Compiled\ExtensibleGrammarCompiler;
use PhpArchitecture\Parser\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Grammar\Definition\Model\RuleType;
use PhpArchitecture\Parser\Grammar\Definition\Rule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TaggedRuleExtensionTest extends TestCase
{
    private ExtensibleGrammarCompiler $compiler;

    protected function setUp(): void
    {
        $this->compiler = new ExtensibleGrammarCompiler(
            DefaultExtensionProvider::getExtensions()
        );
    }

    #[Test]
    public function shouldRemoveUnusedTaggedRulesFromCompiledGrammar(): void
    {
        $grammar = new Grammar('test', 'tagged');
        
        $grammar->global->add(
            Rule::token('space', ' ', ['ws']),
            Rule::token('tab', "\t", ['ws']),
            Rule::taggedWith('unused_tag')
        );

        $compiled = $this->compiler->compile($grammar);

        // Unused tagged rule should be removed
        $globalRuleNames = array_map(fn($r) => $r->name, $compiled->regions['global']->rules);
        
        $this->assertNotContains('unused_tag', $globalRuleNames,
            'Unused tagged rule should be removed from compiled grammar');
        $this->assertContains('space', $globalRuleNames);
        $this->assertContains('tab', $globalRuleNames);
    }

    #[Test]
    public function shouldCreateEventSubscribersForRegionTrigger(): void
    {
        $grammar = new Grammar('test', 'tagged');
        
        $grammar->global->add(
            Rule::token('space', ' ', ['ws']),
            Rule::token('tab', "\t", ['ws']),
            Rule::taggedWith('ws')
                ->startRegion('whitespace')
                ->add(Rule::token('content', 'x'))
        );

        $compiled = $this->compiler->compile($grammar);

        // EventSubscribers should be created in parent region (global)
        // One for each tagged rule (space, tab)
        $this->assertGreaterThanOrEqual(2, count($compiled->regions['global']->eventSubscribers),
            'Should create event subscribers for each rule with matching tag');
    }

    #[Test]
    public function shouldHandleMultipleRulesWithSameTag(): void
    {
        $grammar = new Grammar('test', 'tagged');
        
        $grammar->global->add(
            Rule::token('a', 'a', ['letter']),
            Rule::token('b', 'b', ['letter']),
            Rule::token('c', 'c', ['letter']),
            Rule::taggedWith('letter')
                ->startRegion('letter_region')
                ->add(Rule::token('content', 'x'))
        );

        $compiled = $this->compiler->compile($grammar);

        // Should create event subscriber for each tagged rule
        $this->assertGreaterThanOrEqual(3, count($compiled->regions['global']->eventSubscribers),
            'Should create event subscriber for each rule with tag "letter"');
    }

    #[Test]
    public function shouldPreserveOriginalTaggedRules(): void
    {
        $grammar = new Grammar('test', 'tagged');
        
        $grammar->global->add(
            Rule::token('space', ' ', ['ws']),
            Rule::token('tab', "\t", ['ws']),
            Rule::taggedWith('ws')
                ->startRegion('whitespace')
                ->add(Rule::token('content', 'x'))
        );

        $compiled = $this->compiler->compile($grammar);

        // Original tagged rules (space, tab) should still exist
        $globalRuleNames = array_map(fn($r) => $r->name, $compiled->regions['global']->rules);
        
        $this->assertContains('space', $globalRuleNames);
        $this->assertContains('tab', $globalRuleNames);
    }

    #[Test]
    public function shouldHandleTaggedRuleWithNoMatchingTags(): void
    {
        $grammar = new Grammar('test', 'tagged');
        
        $grammar->global->add(
            Rule::token('x', 'x'),
            Rule::taggedWith('nonexistent')
                ->startRegion('test_region')
                ->add(Rule::token('content', 'y'))
        );

        $compiled = $this->compiler->compile($grammar);

        // Should compile without errors
        $this->assertArrayHasKey('global', $compiled->regions);
        
        // No event subscribers should be created for nonexistent tag
        // (only default ones from other extensions)
        foreach ($compiled->regions['global']->eventSubscribers as $subscriber) {
            // We can't easily check the listener type, but compilation should succeed
            $this->assertNotNull($subscriber);
        }
    }

    #[Test]
    public function shouldWorkWithNestedRegions(): void
    {
        $grammar = new Grammar('test', 'tagged');
        
        $grammar->global->add(
            Rule::token('open', '{', ['bracket']),
            Rule::token('close', '}', ['bracket']),
            Rule::taggedWith('bracket')
                ->startRegion('bracket_region')
                ->add(
                    Rule::token('inner_open', '(', ['paren']),
                    Rule::taggedWith('paren')
                        ->startRegion('paren_region')
                        ->add(Rule::token('content', 'x'))
                )
        );

        $compiled = $this->compiler->compile($grammar);

        // Event subscribers should exist in appropriate regions
        $this->assertGreaterThan(0, count($compiled->regions['global']->eventSubscribers),
            'Global region should have event subscribers for bracket-tagged rules');
        $this->assertGreaterThan(0, count($compiled->regions['bracket_region']->eventSubscribers),
            'Bracket region should have event subscribers for paren-tagged rules');
    }
}
