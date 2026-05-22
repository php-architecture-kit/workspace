<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Func\Foundation\Grammar\Rule;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\EventSubscriber;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Middleware\AddEventSubscriberMiddleware;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Middleware\AddRegionMiddleware;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Middleware\AddRuleMiddleware;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Region;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Foundation\Tokenization\Contract\TokenizationContext;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\TokenAddedEvent;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\TokenRegion;
use PhpArchitecture\Parser\Tests\Func\Grammar\GrammarTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('func')]
final class MiddlewareTest extends GrammarTestCase
{
    #[Test]
    public function shouldAddRuleMiddlewareTransformRuleBeforeAdding(): void
    {
        // AddRuleMiddleware intercepts every Rule added to a region and can transform it.
        // Here it adds tag 'auto' to every rule — verified by checking the token's tags.
        $grammar = new Grammar('middleware-test');
        $grammar->global->add(
            AddRuleMiddleware::fromCallable(fn(Rule $r) => $r->addTag('auto')),
        );
        $grammar->global->add(Rule::token('x', 'x'));

        $this->assertGrammarParsing(
            string: 'x',
            grammar: $grammar,
            assertTokenizationResultValid: function (TokenRegion $tokenRegion, self $test): void {
                $token = $tokenRegion->stream->tokens[0];

                $test->assertContains('auto', $token->tags);
            },
            requireBofEof: false,
        );
    }

    #[Test]
    public function shouldAddRegionMiddlewareTransformRegionBeforeAdding(): void
    {
        // AddRegionMiddleware intercepts every Region added to a region.
        // Here it adds tag 'auto-region' to any region — verified via TokenRegion.tags.
        $grammar = new Grammar('middleware-test');
        $grammar->global->add(
            AddRegionMiddleware::fromCallable(fn(Region $r) => $r->addTag('auto-region')),
        );

        $inner = (new Region('inner'))
            ->openWith(Rule::token('open', '['), includeOpenRuleMatch: true)
            ->closeWith(Rule::token('close', ']'), includeCloseRuleMatch: true);
        $grammar->global->add($inner);

        $this->assertGrammarParsing(
            string: '[]',
            grammar: $grammar,
            assertTokenizationResultValid: function (TokenRegion $tokenRegion, self $test): void {
                $innerRegion = $tokenRegion->stream->tokens[0];
                $test->assertInstanceOf(TokenRegion::class, $innerRegion);

                $test->assertContains('auto-region', $innerRegion->tags);
            },
            requireBofEof: false,
        );
    }

    #[Test]
    public function shouldAddEventSubscriberMiddlewareTransformSubscriberBeforeAdding(): void
    {
        // AddEventSubscriberMiddleware intercepts every EventSubscriber added to a region.
        // Here it restricts all subscribers to fire only for rule 'x' — so the subscriber
        // that would normally fire for any token only fires when 'x' is added.
        $firedFor = [];
        $grammar = new Grammar('middleware-test');
        $grammar->global->add(
            AddEventSubscriberMiddleware::fromCallable(
                fn(EventSubscriber $s) => $s->onlyForRuleName('x'),
            ),
        );
        $grammar->global->add(Rule::token('x', 'x'));
        $grammar->global->add(Rule::token('y', 'y'));
        $grammar->global->add(
            EventSubscriber::on(
                TokenAddedEvent::class,
                function (TokenAddedEvent $event, TokenizationContext $ctx) use (&$firedFor): void {
                    $firedFor[] = $event->token->name;
                },
            ),
        );

        $this->assertGrammarParsing(string: 'xy', grammar: $grammar, requireBofEof: false);

        $this->assertSame(['x'], $firedFor);
    }
}
