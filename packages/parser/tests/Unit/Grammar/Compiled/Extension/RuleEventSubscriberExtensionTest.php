<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Unit\Grammar\Compiled\Extension;

use PhpArchitecture\Parser\Grammar\Compiled\Extension\RuleEventSubscriberExtension;
use PhpArchitecture\Parser\Grammar\Definition\EventSubscriber;
use PhpArchitecture\Parser\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Processing\Event\Tokenization\TokenAddedEvent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RuleEventSubscriberExtensionTest extends TestCase
{
    private RuleEventSubscriberExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new RuleEventSubscriberExtension();
    }

    #[Test]
    public function shouldMoveEventSubscribersFromRuleToRegion(): void
    {
        $grammar = new Grammar('test', 'variant');
        
        $rule = Rule::token('test', 'x');
        $rule->onEvent(
            TokenAddedEvent::class,
            static fn($event, $context) => null
        );
        
        $grammar->global->add($rule);

        $this->assertCount(0, $grammar->global->eventSubscribers);
        
        $this->extension->apply($grammar);

        $this->assertCount(1, $grammar->global->eventSubscribers);
    }

    #[Test]
    public function shouldHandleMultipleEventSubscribersOnSingleRule(): void
    {
        $grammar = new Grammar('test', 'variant');
        
        $rule = Rule::token('test', 'x');
        $rule->onEvent(TokenAddedEvent::class, static fn() => 'a');
        $rule->onEvent(TokenAddedEvent::class, static fn() => 'b');
        
        $grammar->global->add($rule);

        $this->extension->apply($grammar);

        $this->assertCount(2, $grammar->global->eventSubscribers);
    }

    #[Test]
    public function shouldHandleRulesWithoutEventSubscribers(): void
    {
        $grammar = new Grammar('test', 'variant');
        $grammar->global->add(Rule::token('test', 'x'));

        $this->extension->apply($grammar);

        $this->assertCount(0, $grammar->global->eventSubscribers);
    }

    #[Test]
    public function shouldHaveCorrectPriority(): void
    {
        $this->assertSame(100, $this->extension->priority());
    }
}
