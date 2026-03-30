<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Unit\Grammar\Compiled\Extension;

use PhpArchitecture\Parser\Grammar\Compiled\Extension\TaggedRuleExtension;
use PhpArchitecture\Parser\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Grammar\Definition\Region;
use PhpArchitecture\Parser\Grammar\Definition\Rule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TaggedRuleExtensionTest extends TestCase
{
    private TaggedRuleExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new TaggedRuleExtension();
    }

    #[Test]
    public function shouldResolveTaggedRuleForRegionStart(): void
    {
        $grammar = new Grammar('test', 'variant');
        
        $grammar->global->add(
            Rule::token('space', ' ', ['ws']),
            Rule::token('tab', "\t", ['ws']),
            Rule::taggedWith('ws')
                ->startRegion('whitespace')
                ->add(Rule::token('more_space', ' '))
        );

        // OpenCloseRuleExtension must run first to add tagged rule to parent region
        $openCloseExtension = new \PhpArchitecture\Parser\Grammar\Compiled\Extension\OpenCloseRuleExtension();
        $openCloseExtension->apply($grammar);
        
        $initialSubscriberCount = count($grammar->global->eventSubscribers);
        
        $this->extension->apply($grammar);

        // Should add event subscribers for each tagged rule (space and tab)
        $this->assertGreaterThan($initialSubscriberCount, count($grammar->global->eventSubscribers));
    }

    #[Test]
    public function shouldRemoveTaggedRulePlaceholder(): void
    {
        $grammar = new Grammar('test', 'variant');
        
        $grammar->global->add(
            Rule::token('x', 'x', ['tag1']),
            Rule::taggedWith('tag1')->startRegion('test_region')
        );

        $this->extension->apply($grammar);

        // Tagged rule placeholder should be removed, only 'x' token should remain
        foreach ($grammar->global->rules as $rule) {
            $this->assertNotSame(\PhpArchitecture\Parser\Grammar\Definition\Model\RuleType::Tag, $rule->type);
        }
    }

    #[Test]
    public function shouldHandleNoMatchingTags(): void
    {
        $grammar = new Grammar('test', 'variant');
        
        $grammar->global->add(
            Rule::taggedWith('nonexistent')->startRegion('test')
        );

        $this->extension->apply($grammar);

        $this->assertCount(0, $grammar->global->eventSubscribers);
    }

    #[Test]
    public function shouldHaveCorrectPriority(): void
    {
        $this->assertSame(200, $this->extension->priority());
    }
}
