<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Func\Foundation\Grammar\Definition\Region;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\EventSubscriber;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Region;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\NodeAttribute;
use PhpArchitecture\Parser\Foundation\Tokenization\Contract\TokenizationContext;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\TokenRegionEndedEvent;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\TokenRegion;
use PhpArchitecture\Parser\Tests\Func\Grammar\GrammarTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('func')]
final class RegionMetaTest extends GrammarTestCase
{
    #[Test]
    public function shouldMetaBeAvailableAtEveryParsingStage(): void
    {
        // $region->setMeta() sets arbitrary key-value metadata on a Region at definition time.
        // That meta is accessible on the Region definition object.
        // At tokenization time, meta can be set on the resulting TokenRegion via an EventSubscriber
        // (here we copy the same key/value to demonstrate the full round-trip).
        // The TokenRegion meta then flows into the NodeAttribute and inner Node at parsing time.
        $grammar = new Grammar('meta-test');

        $inner = (new Region('inner'))
            ->openWith(Rule::token('open', '['), includeOpenRuleMatch: true)
            ->closeWith(Rule::token('close', ']'), includeCloseRuleMatch: true);
        $inner->add(Rule::expr('content', '[a-z]+'));
        $inner->setMeta('marker', 'test-value');

        $inner->add(
            EventSubscriber::on(
                TokenRegionEndedEvent::class,
                function (TokenRegionEndedEvent $event, TokenizationContext $ctx): void {
                    $event->region->setMeta('marker', 'test-value');
                },
            ),
        );

        $grammar->global->add($inner);

        $this->assertGrammarParsing(
            string: '[hello]',
            grammar: $grammar,
            assertDefinedGrammarValid: function (Grammar $grammar, self $test): void {
                $regions = $grammar->getAllRegions();
                $test->assertArrayHasKey('inner', $regions);
                $test->assertSame('test-value', $regions['inner']->getMeta('marker'));
            },
            assertTokenizationResultValid: function (TokenRegion $tokenRegion, self $test): void {
                $innerRegion = $tokenRegion->stream->tokens[0];
                $test->assertInstanceOf(TokenRegion::class, $innerRegion);
                $test->assertSame('test-value', $innerRegion->getMeta('marker'));
            },
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $attributes = $node->getAttributes();
                $test->assertCount(1, $attributes);

                $innerAttr = $attributes[0];
                $test->assertInstanceOf(NodeAttribute::class, $innerAttr);
                $test->assertSame('test-value', $innerAttr->getMeta('marker'));
                $test->assertSame('test-value', $innerAttr->node->getMeta('marker'));
            },
            requireBofEof: false,
        );
    }
}
