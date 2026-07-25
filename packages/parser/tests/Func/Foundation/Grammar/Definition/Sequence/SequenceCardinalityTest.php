<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Func\Foundation\Grammar\Definition\Sequence;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\GroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\NodeAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\OptionalAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawContentAttribute;
use PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Technical\Whitespace;
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

    // --- Trivia tags (-, -l, -t) ---
    //
    // The trivia markers resolve through a different path than direct `/n` tagging:
    // `-`/`-l`/`-t` are tags on Whitespace's `whitespace` region, expanded by
    // SequenceNodeEnricher's tag-spreading (and, for `whitespace`, a self-referential
    // tag-on-its-own-region edge case it must not mistake for "no type"). These tests
    // pin down that the cardinality-to-attribute-class mapping holds for that path too,
    // not just for tokens tagged `/n` directly.

    private function triviaGrammar(string $rootSequence): Grammar
    {
        $grammar = (new Whitespace())->grammar();
        $grammar->global->add(
            Rule::token('open', '['),
            Rule::token('close', ']'),
        );
        $grammar->global->withRootSequence($rootSequence);

        return $grammar;
    }

    #[Test]
    public function shouldProduceGroupAttributeForZeroOrMoreLeadingTrivia(): void
    {
        $this->assertGrammarParsing(
            string: '[  ]',
            grammar: $this->triviaGrammar('open -l* close'),
            requireBofEof: false,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $trivia = $node->getAttributes()[1];

                $test->assertInstanceOf(GroupAttribute::class, $trivia, '`-l*` is zero-or-more, so it is a GroupAttribute even though it matched once');
                $test->assertSame('  ', (string) $node->getAttributes()[1]);
            },
        );
    }

    #[Test]
    public function shouldProduceEmptyGroupAttributeWhenZeroOrMoreLeadingTriviaMatchesNothing(): void
    {
        $this->assertGrammarParsing(
            string: '[]',
            grammar: $this->triviaGrammar('open -l* close'),
            requireBofEof: false,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $trivia = $node->getAttributes()[1];

                $test->assertInstanceOf(GroupAttribute::class, $trivia, 'no match still produces a GroupAttribute, not an absent attribute');
                $test->assertCount(0, $trivia->nodes);
            },
        );
    }

    #[Test]
    public function shouldProduceNullOptionalAttributeWhenZeroOrOneTrailingTriviaIsAbsent(): void
    {
        $this->assertGrammarParsing(
            string: '[]',
            grammar: $this->triviaGrammar('open ?-t close'),
            requireBofEof: false,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $trivia = $node->getAttributes()[1];

                $test->assertInstanceOf(OptionalAttribute::class, $trivia, '`?-t` is zero-or-one, so it is an OptionalAttribute');
                $test->assertNull($trivia->node);
            },
        );
    }

    #[Test]
    public function shouldOptionalAttributeHoldNodeWhenZeroOrOneTrailingTriviaIsPresent(): void
    {
        $this->assertGrammarParsing(
            string: '[ ]',
            grammar: $this->triviaGrammar('open ?-t close'),
            requireBofEof: false,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $trivia = $node->getAttributes()[1];

                $test->assertInstanceOf(OptionalAttribute::class, $trivia);
                $test->assertNotNull($trivia->node);
                $test->assertSame(' ', (string) $trivia->node);
            },
        );
    }

    #[Test]
    public function shouldProduceNodeAttributeForRequiredLeadingTrivia(): void
    {
        $this->assertGrammarParsing(
            string: '[ ]',
            grammar: $this->triviaGrammar('open -l close'),
            requireBofEof: false,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $trivia = $node->getAttributes()[1];

                $test->assertInstanceOf(NodeAttribute::class, $trivia, 'bare `-l` is exactly-one, so it is a plain NodeAttribute');
                $test->assertSame(' ', (string) $trivia->node);
            },
        );
    }
}
