<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Func\Foundation\Grammar\Definition\Sequence;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\GroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\OptionalAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\RawContentAttribute;
use PhpArchitecture\Parser\Tests\Func\Grammar\GrammarTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('func')]
final class SequenceCardinalityTest extends GrammarTestCase
{
    // --- Optional (?) ---

    #[Test]
    public function shouldCastNodeToStringWithoutAbsentOptionalElement(): void
    {
        // ?content absent: string output should skip the missing part.
        $grammar = new Grammar('cardinality-test');
        $grammar->global->add(Rule::token('open', '['));
        $grammar->global->add(Rule::token('close', ']'));
        $grammar->global->add(Rule::token('content', 'x'));
        $grammar->global->withRootSequence('open ?content close');

        $this->assertGrammarParsing(
            string: '[]',
            grammar: $grammar,
            requireBofEof: false,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $test->assertSame('[]', (string) $node);
            },
        );
    }

    #[Test]
    public function shouldCastNodeToStringWithPresentOptionalElement(): void
    {
        $grammar = new Grammar('cardinality-test');
        $grammar->global->add(Rule::token('open', '['));
        $grammar->global->add(Rule::token('close', ']'));
        $grammar->global->add(Rule::token('content', 'x'));
        $grammar->global->withRootSequence('open ?content close');

        $this->assertGrammarParsing(
            string: '[x]',
            grammar: $grammar,
            requireBofEof: false,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $test->assertSame('[x]', (string) $node);
            },
        );
    }

    #[Test]
    public function shouldProduceOptionalAttributeWhenNodeTypeIsNode(): void
    {
        // /n inline tag forces NodeType::Node on the sequence node → OptionalAttribute
        $grammar = new Grammar('cardinality-test');
        $grammar->global->add(Rule::token('open', '['));
        $grammar->global->add(Rule::token('close', ']'));
        $grammar->global->add(Rule::token('content', 'x'));
        $grammar->global->withRootSequence('open ?content/n close');

        $this->assertGrammarParsing(
            string: '[]',
            grammar: $grammar,
            requireBofEof: false,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $attributes = $node->getAttributes();

                $test->assertCount(3, $attributes);
                $test->assertInstanceOf(OptionalAttribute::class, $attributes[1]);
                $test->assertNull($attributes[1]->node);
            },
        );
    }

    #[Test]
    public function shouldOptionalAttributeContainNodeWhenElementPresent(): void
    {
        $grammar = new Grammar('cardinality-test');
        $grammar->global->add(Rule::token('open', '['));
        $grammar->global->add(Rule::token('close', ']'));
        $grammar->global->add(Rule::token('content', 'x'));
        $grammar->global->withRootSequence('open ?content/n close');

        $this->assertGrammarParsing(
            string: '[x]',
            grammar: $grammar,
            requireBofEof: false,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $optionalAttr = $node->getAttributes()[1];

                $test->assertInstanceOf(OptionalAttribute::class, $optionalAttr);
                $test->assertInstanceOf(NodeInterface::class, $optionalAttr->node);
                $test->assertSame('x', (string) $optionalAttr->node);
            },
        );
    }

    // --- One or more (+) ---

    #[Test]
    public function shouldMatchOneOccurrenceForOneOrMore(): void
    {
        $grammar = new Grammar('cardinality-test');
        $grammar->global->add(Rule::token('x', 'x'));
        $grammar->global->withRootSequence('x+');

        $this->assertGrammarParsing(
            string: 'x',
            grammar: $grammar,
            requireBofEof: false,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $test->assertSame('x', (string) $node);
            },
        );
    }

    #[Test]
    public function shouldMatchMultipleOccurrencesForOneOrMore(): void
    {
        $grammar = new Grammar('cardinality-test');
        $grammar->global->add(Rule::token('x', 'x'));
        $grammar->global->withRootSequence('x+');

        $this->assertGrammarParsing(
            string: 'xxx',
            grammar: $grammar,
            requireBofEof: false,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $test->assertSame('xxx', (string) $node);
            },
        );
    }

    #[Test]
    public function shouldProduceGroupAttributeForOneOrMoreWhenNodeTypeIsNode(): void
    {
        // /n inline tag forces NodeType::Node → GroupAttribute
        $grammar = new Grammar('cardinality-test');
        $grammar->global->add(Rule::token('x', 'x'));
        $grammar->global->withRootSequence('x+/n');

        $this->assertGrammarParsing(
            string: 'xxx',
            grammar: $grammar,
            requireBofEof: false,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $attributes = $node->getAttributes();

                $test->assertCount(1, $attributes);
                $test->assertInstanceOf(GroupAttribute::class, $attributes[0]);
                $test->assertCount(3, $attributes[0]->nodes);
            },
        );
    }

    // --- Zero or more (*) ---

    #[Test]
    public function shouldMatchZeroOccurrencesForZeroOrMore(): void
    {
        $grammar = new Grammar('cardinality-test');
        $grammar->global->add(Rule::token('open', '['));
        $grammar->global->add(Rule::token('close', ']'));
        $grammar->global->add(Rule::token('x', 'x'));
        $grammar->global->withRootSequence('open x* close');

        $this->assertGrammarParsing(
            string: '[]',
            grammar: $grammar,
            requireBofEof: false,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $test->assertSame('[]', (string) $node);
            },
        );
    }

    #[Test]
    public function shouldMatchMultipleOccurrencesForZeroOrMore(): void
    {
        $grammar = new Grammar('cardinality-test');
        $grammar->global->add(Rule::token('open', '['));
        $grammar->global->add(Rule::token('close', ']'));
        $grammar->global->add(Rule::token('x', 'x'));
        $grammar->global->withRootSequence('open x* close');

        $this->assertGrammarParsing(
            string: '[xxx]',
            grammar: $grammar,
            requireBofEof: false,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $test->assertSame('[xxx]', (string) $node);
            },
        );
    }

    #[Test]
    public function shouldProduceGroupAttributeForZeroOrMoreWhenNodeTypeIsNode(): void
    {
        $grammar = new Grammar('cardinality-test');
        $grammar->global->add(Rule::token('open', '['));
        $grammar->global->add(Rule::token('close', ']'));
        $grammar->global->add(Rule::token('x', 'x'));
        $grammar->global->withRootSequence('open x*/n close');

        $this->assertGrammarParsing(
            string: '[xxx]',
            grammar: $grammar,
            requireBofEof: false,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $groupAttr = $node->getAttributes()[1];

                $test->assertInstanceOf(GroupAttribute::class, $groupAttr);
                $test->assertCount(3, $groupAttr->nodes);
            },
        );
    }

    #[Test]
    public function shouldProduceEmptyGroupAttributeWhenZeroOrMoreMatchesNothing(): void
    {
        $grammar = new Grammar('cardinality-test');
        $grammar->global->add(Rule::token('open', '['));
        $grammar->global->add(Rule::token('close', ']'));
        $grammar->global->add(Rule::token('x', 'x'));
        $grammar->global->withRootSequence('open x*/n close');

        $this->assertGrammarParsing(
            string: '[]',
            grammar: $grammar,
            requireBofEof: false,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $groupAttr = $node->getAttributes()[1];

                $test->assertInstanceOf(GroupAttribute::class, $groupAttr);
                $test->assertCount(0, $groupAttr->nodes);
            },
        );
    }
}
