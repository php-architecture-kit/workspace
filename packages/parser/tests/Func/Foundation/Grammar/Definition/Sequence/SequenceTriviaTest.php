<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Func\Foundation\Grammar\Definition\Sequence;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\RawContentAttribute;
use PhpArchitecture\Parser\Tests\Func\Grammar\GrammarTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('func')]
final class SequenceTriviaTest extends GrammarTestCase
{
    // Rule named '-' acts as trivia placeholder. TriviaSequenceNamingMiddleware
    // assigns anchor names automatically based on position in the sequence.

    #[Test]
    public function shouldMatchSequenceWithTrivia(): void
    {
        $grammar = new Grammar('trivia-test');
        $grammar->global->add(Rule::expr('-', '\s+'));
        $grammar->global->add(Rule::token('open', '['));
        $grammar->global->add(Rule::token('close', ']'));
        $grammar->global->withRootSequence('open - close');

        $this->assertGrammarParsing(
            string: '[ ]',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $test->assertSame('[ ]', (string) $node);
            },
            requireBofEof: false,
        );
    }

    #[Test]
    public function shouldSingleTriviaGetAnchorNameTrivia(): void
    {
        $grammar = new Grammar('trivia-test');
        $grammar->global->add(Rule::expr('-', '\s+'));
        $grammar->global->add(Rule::token('open', '['));
        $grammar->global->add(Rule::token('close', ']'));
        $grammar->global->withRootSequence('open - close');

        $this->assertGrammarParsing(
            string: '[ ]',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $attributes = $node->getAttributes();

                $test->assertCount(3, $attributes);
                $test->assertSame('trivia', $attributes[1]->getName());
            },
            requireBofEof: false,
        );
    }

    #[Test]
    public function shouldFirstTriviaGetLeadingAnchorName(): void
    {
        $grammar = new Grammar('trivia-test');
        $grammar->global->add(Rule::expr('-', '\s+'));
        $grammar->global->add(Rule::token('value', 'x'));
        $grammar->global->withRootSequence('- value -');

        $this->assertGrammarParsing(
            string: ' x ',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $attributes = $node->getAttributes();

                $test->assertCount(3, $attributes);
                $test->assertSame('leadingTrivia', $attributes[0]->getName());
            },
            requireBofEof: false,
        );
    }

    #[Test]
    public function shouldLastTriviaGetTrailingAnchorName(): void
    {
        $grammar = new Grammar('trivia-test');
        $grammar->global->add(Rule::expr('-', '\s+'));
        $grammar->global->add(Rule::token('value', 'x'));
        $grammar->global->withRootSequence('- value -');

        $this->assertGrammarParsing(
            string: ' x ',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $attributes = $node->getAttributes();

                $test->assertCount(3, $attributes);
                $test->assertSame('trailingTrivia', $attributes[2]->getName());
            },
            requireBofEof: false,
        );
    }

    #[Test]
    public function shouldMiddleTriviaGetInlineAnchorName(): void
    {
        $grammar = new Grammar('trivia-test');
        $grammar->global->add(Rule::expr('-', '\s+'));
        $grammar->global->add(Rule::token('a', 'a'));
        $grammar->global->add(Rule::token('b', 'b'));
        $grammar->global->withRootSequence('- a - b -');

        $this->assertGrammarParsing(
            string: ' a b ',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $attributes = $node->getAttributes();

                $test->assertCount(5, $attributes);
                $test->assertSame('leadingTrivia', $attributes[0]->getName());
                $test->assertSame('inlineTrivia', $attributes[2]->getName());
                $test->assertSame('trailingTrivia', $attributes[4]->getName());
            },
            requireBofEof: false,
        );
    }

    #[Test]
    public function shouldCastNodeToStringWithTrivia(): void
    {
        $grammar = new Grammar('trivia-test');
        $grammar->global->add(Rule::expr('-', '\s+'));
        $grammar->global->add(Rule::token('open', '['));
        $grammar->global->add(Rule::token('close', ']'));
        $grammar->global->withRootSequence('open - close');

        $this->assertGrammarParsing(
            string: '[  ]',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $test->assertSame('[  ]', (string) $node);
            },
            requireBofEof: false,
        );
    }

    #[Test]
    public function shouldTriviaAttributeContainWhitespaceContent(): void
    {
        $grammar = new Grammar('trivia-test');
        $grammar->global->add(Rule::expr('-', '\s+'));
        $grammar->global->add(Rule::token('open', '['));
        $grammar->global->add(Rule::token('close', ']'));
        $grammar->global->withRootSequence('open - close');

        $this->assertGrammarParsing(
            string: '[   ]',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $attributes = $node->getAttributes();

                $test->assertInstanceOf(RawContentAttribute::class, $attributes[1]);
                $test->assertSame('   ', $attributes[1]->content);
            },
            requireBofEof: false,
        );
    }
}
