<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Func\Foundation\Grammar\Rule;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\EventSubscriber;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Foundation\Tokenization\Contract\TokenizationContext;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\TokenAddedEvent;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\TokenMatchedEvent;
use PhpArchitecture\Parser\Tests\Func\Grammar\GrammarTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('func')]
final class EventTest extends GrammarTestCase
{
    #[Test]
    public function shouldFireListenerWhenTokenMatched(): void
    {
        $fired = false;
        $grammar = new Grammar('event-test');
        $grammar->global->add(
            Rule::token('x', 'x')->onEvent(
                TokenMatchedEvent::class,
                function (TokenMatchedEvent $event, TokenizationContext $ctx) use (&$fired): void {
                    $fired = true;
                },
            ),
        );

        $this->assertGrammarParsing(string: 'x', grammar: $grammar, requireBofEof: false);

        $this->assertTrue($fired);
    }

    #[Test]
    public function shouldFireListenerWhenTokenAdded(): void
    {
        $fired = false;
        $grammar = new Grammar('event-test');
        $grammar->global->add(
            Rule::token('x', 'x')->onEvent(
                TokenAddedEvent::class,
                function (TokenAddedEvent $event, TokenizationContext $ctx) use (&$fired): void {
                    $fired = true;
                },
            ),
        );

        $this->assertGrammarParsing(string: 'x', grammar: $grammar, requireBofEof: false);

        $this->assertTrue($fired);
    }

    #[Test]
    public function shouldFireEventSubscriberAddedDirectlyToRegion(): void
    {
        $fired = false;
        $grammar = new Grammar('event-test');
        $grammar->global->add(Rule::token('x', 'x'));
        $grammar->global->add(
            EventSubscriber::on(
                TokenAddedEvent::class,
                function (TokenAddedEvent $event, TokenizationContext $ctx) use (&$fired): void {
                    $fired = true;
                },
            ),
        );

        $this->assertGrammarParsing(string: 'x', grammar: $grammar, requireBofEof: false);

        $this->assertTrue($fired);
    }

    #[Test]
    public function shouldFilterEventSubscriberByRuleName(): void
    {
        $firedFor = [];
        $grammar = new Grammar('event-test');
        $grammar->global->add(Rule::token('x', 'x'));
        $grammar->global->add(Rule::token('y', 'y'));
        $grammar->global->add(
            EventSubscriber::on(
                TokenAddedEvent::class,
                function (TokenAddedEvent $event, TokenizationContext $ctx) use (&$firedFor): void {
                    $firedFor[] = $event->token->name;
                },
            )->onlyForRuleName('x'),
        );

        $this->assertGrammarParsing(string: 'xy', grammar: $grammar, requireBofEof: false);

        $this->assertSame(['x'], $firedFor);
    }

    #[Test]
    public function shouldRespectEventSubscriberPriority(): void
    {
        // Lower priority number = earlier execution (ascending sort in dispatcher).
        $order = [];
        $grammar = new Grammar('event-test');
        $grammar->global->add(Rule::token('x', 'x'));
        $grammar->global->add(
            EventSubscriber::on(
                TokenAddedEvent::class,
                function (TokenAddedEvent $event, TokenizationContext $ctx) use (&$order): void {
                    $order[] = 'second';
                },
            )->onlyForRuleName('x')->priority(10),
        );
        $grammar->global->add(
            EventSubscriber::on(
                TokenAddedEvent::class,
                function (TokenAddedEvent $event, TokenizationContext $ctx) use (&$order): void {
                    $order[] = 'first';
                },
            )->onlyForRuleName('x')->priority(1),
        );

        $this->assertGrammarParsing(string: 'x', grammar: $grammar, requireBofEof: false);

        $this->assertSame(['first', 'second'], $order);
    }
}
