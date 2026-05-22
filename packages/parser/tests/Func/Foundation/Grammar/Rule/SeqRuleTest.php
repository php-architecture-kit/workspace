<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Func\Foundation\Grammar\Rule;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\NodeAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\RawContentAttribute;
use PhpArchitecture\Parser\Tests\Func\Grammar\GrammarTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('func')]
final class SeqRuleTest extends GrammarTestCase
{
    // --- rootSequence (inline sequence, no Rule::seq) ---

    #[Test]
    public function shouldMatchTokensInDefinedOrder(): void
    {
        $grammar = new Grammar('seq-test');
        $grammar->global->add(Rule::token('open', '['));
        $grammar->global->add(Rule::token('close', ']'));
        $grammar->global->withRootSequence('open close');

        $this->assertGrammarParsing(
            string: '[]',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $test->assertSame('[]', (string) $node);
            },
            requireBofEof: false,
        );
    }

    #[Test]
    public function shouldProduceAttributeForEachTokenInInlineSequence(): void
    {
        $grammar = new Grammar('seq-test');
        $grammar->global->add(Rule::token('open', '['));
        $grammar->global->add(Rule::token('close', ']'));
        $grammar->global->withRootSequence('open close');

        $this->assertGrammarParsing(
            string: '[]',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $attributes = $node->getAttributes();

                $test->assertCount(2, $attributes);
                $test->assertInstanceOf(RawContentAttribute::class, $attributes[0]);
                $test->assertInstanceOf(RawContentAttribute::class, $attributes[1]);
                $test->assertSame('[', $attributes[0]->content);
                $test->assertSame(']', $attributes[1]->content);
            },
            requireBofEof: false,
        );
    }

    // --- Rule::seq ---

    #[Test]
    public function shouldGroupTokensUnderNamedNodeAttribute(): void
    {
        $grammar = new Grammar('seq-test');
        $grammar->global->add(Rule::token('open', '['));
        $grammar->global->add(Rule::token('close', ']'));
        $grammar->global->add(Rule::seq('brackets', 'open close'));
        $grammar->global->withRootSequence('brackets');

        $this->assertGrammarParsing(
            string: '[]',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $attributes = $node->getAttributes();

                $test->assertCount(1, $attributes);
                $test->assertInstanceOf(NodeAttribute::class, $attributes[0]);
                $test->assertSame('brackets', $attributes[0]->name);
            },
            requireBofEof: false,
        );
    }

    #[Test]
    public function shouldChildNodeContainAttributesForEachToken(): void
    {
        $grammar = new Grammar('seq-test');
        $grammar->global->add(Rule::token('open', '['));
        $grammar->global->add(Rule::token('close', ']'));
        $grammar->global->add(Rule::seq('brackets', 'open close'));
        $grammar->global->withRootSequence('brackets');

        $this->assertGrammarParsing(
            string: '[]',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $childNode = $node->getAttributes()[0];
                $test->assertInstanceOf(NodeAttribute::class, $childNode);

                $childAttributes = $childNode->node->getAttributes();
                $test->assertCount(2, $childAttributes);
                $test->assertInstanceOf(RawContentAttribute::class, $childAttributes[0]);
                $test->assertInstanceOf(RawContentAttribute::class, $childAttributes[1]);
            },
            requireBofEof: false,
        );
    }

    #[Test]
    public function shouldCastNodeToStringAsMatchedInput(): void
    {
        $grammar = new Grammar('seq-test');
        $grammar->global->add(Rule::token('open', '['));
        $grammar->global->add(Rule::token('close', ']'));
        $grammar->global->add(Rule::seq('brackets', 'open close'));
        $grammar->global->withRootSequence('brackets');

        $this->assertGrammarParsing(
            string: '[]',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $test->assertSame('[]', (string) $node);
            },
            requireBofEof: false,
        );
    }
}
