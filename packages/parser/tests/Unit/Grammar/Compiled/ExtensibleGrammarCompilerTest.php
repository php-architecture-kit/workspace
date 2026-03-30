<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Unit\Grammar\Compiled;

use PhpArchitecture\Parser\Grammar\Compiled\Extension\DefaultExtensionProvider;
use PhpArchitecture\Parser\Grammar\Compiled\ExtensibleGrammarCompiler;
use PhpArchitecture\Parser\Grammar\Definition\EventSubscriber;
use PhpArchitecture\Parser\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Grammar\Definition\Region;
use PhpArchitecture\Parser\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Processing\Event\Tokenization\TokenAddedEvent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ExtensibleGrammarCompilerTest extends TestCase
{
    private ExtensibleGrammarCompiler $compiler;

    protected function setUp(): void
    {
        $this->compiler = new ExtensibleGrammarCompiler(
            DefaultExtensionProvider::getExtensions()
        );
    }

    #[Test]
    public function shouldCompileSimpleGrammar(): void
    {
        $grammar = new Grammar('test', 'simple');
        $grammar->global->add(
            Rule::token('space', ' '),
            Rule::token('word', '[a-z]+')
        );

        $compiled = $this->compiler->compile($grammar);

        $this->assertSame('test', $compiled->name);
        $this->assertSame('simple', $compiled->variant);
        $this->assertCount(1, $compiled->regions);
        $this->assertArrayHasKey('global', $compiled->regions);
        
        $globalRegion = $compiled->regions['global'];
        $this->assertCount(2, $globalRegion->rules);
        $this->assertTrue($globalRegion->metadata['isRoot']);
    }

    #[Test]
    public function shouldCompileNestedRegions(): void
    {
        $grammar = new Grammar('test', 'nested');
        
        $childRegion = new Region('child');
        $childRegion->add(Rule::token('inner', 'x'));
        
        $grammar->global->add(
            Rule::token('outer', 'y'),
            $childRegion
        );

        $compiled = $this->compiler->compile($grammar);

        $this->assertCount(2, $compiled->regions);
        $this->assertArrayHasKey('global', $compiled->regions);
        $this->assertArrayHasKey('child', $compiled->regions);
        
        $this->assertSame('global', $compiled->regions['child']->parentRegionName);
    }

    #[Test]
    public function shouldDeduplicateEventSubscribers(): void
    {
        $grammar = new Grammar('test', 'dedup');
        
        $listener = static fn($event, $context) => null;
        
        $grammar->global->add(
            EventSubscriber::on(TokenAddedEvent::class, $listener),
            EventSubscriber::on(TokenAddedEvent::class, $listener),
            EventSubscriber::on(TokenAddedEvent::class, $listener)
        );

        $compiled = $this->compiler->compile($grammar);

        $globalRegion = $compiled->regions['global'];
        $this->assertCount(1, $globalRegion->eventSubscribers);
    }

    #[Test]
    public function shouldConvertClosuresToListeners(): void
    {
        $grammar = new Grammar('test', 'closures');
        
        $grammar->global->add(
            EventSubscriber::on(
                TokenAddedEvent::class,
                static fn($event, $context) => null
            )
        );

        $compiled = $this->compiler->compile($grammar);

        $globalRegion = $compiled->regions['global'];
        $this->assertCount(1, $globalRegion->eventSubscribers);
        
        $subscriber = $globalRegion->eventSubscribers[0];
        $this->assertNotInstanceOf(\Closure::class, $subscriber->listener);
    }

    #[Test]
    public function shouldPreserveRuleTags(): void
    {
        $grammar = new Grammar('test', 'tags');
        $grammar->global->add(
            Rule::token('tagged', 'x', ['tag1', 'tag2'])
        );

        $compiled = $this->compiler->compile($grammar);

        $globalRegion = $compiled->regions['global'];
        $rule = $globalRegion->rules[0];
        
        $this->assertContains('tag1', $rule->tags);
        $this->assertContains('tag2', $rule->tags);
    }

    #[Test]
    public function shouldSortEventSubscribersByPriority(): void
    {
        $grammar = new Grammar('test', 'priority');
        
        $grammar->global->add(
            EventSubscriber::on(TokenAddedEvent::class, static fn() => 'low')->priority(10),
            EventSubscriber::on(TokenAddedEvent::class, static fn() => 'high')->priority(100),
            EventSubscriber::on(TokenAddedEvent::class, static fn() => 'medium')->priority(50)
        );

        $compiled = $this->compiler->compile($grammar);

        $globalRegion = $compiled->regions['global'];
        $priorities = array_map(fn($s) => $s->priority, $globalRegion->eventSubscribers);
        
        $this->assertSame([100, 50, 10], $priorities);
    }

    #[Test]
    public function shouldMarkRootRegion(): void
    {
        $grammar = new Grammar('test', 'root');
        
        $rootRegion = new Region('custom_root');
        $rootRegion->add(Rule::token('x', 'x'));
        
        $grammar->global->add($rootRegion);
        $grammar->setRootRegion($rootRegion);

        $compiled = $this->compiler->compile($grammar);

        $this->assertTrue($compiled->regions['custom_root']->metadata['isRoot']);
        $this->assertFalse($compiled->regions['global']->metadata['isRoot']);
    }

    #[Test]
    public function shouldHandleEmptyGrammar(): void
    {
        $grammar = new Grammar('test', 'empty');

        $compiled = $this->compiler->compile($grammar);

        $this->assertCount(1, $compiled->regions);
        $this->assertArrayHasKey('global', $compiled->regions);
        $this->assertEmpty($compiled->regions['global']->rules);
    }

    #[Test]
    public function shouldPreserveRegionMetadata(): void
    {
        $grammar = new Grammar('test', 'metadata');
        $grammar->global->setMeta('custom_key', 'custom_value');
        $grammar->global->addTag('grammar_tag');

        $compiled = $this->compiler->compile($grammar);

        $globalRegion = $compiled->regions['global'];
        $this->assertContains('grammar_tag', $globalRegion->metadata['tags']);
    }
}
