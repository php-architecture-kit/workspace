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
final class SequenceGroupingTest extends GrammarTestCase
{
    #[Test]
    public function shouldMatchGroupExactlyOnce(): void
    {
        $grammar = new Grammar('grouping-test');
        $grammar->global->add(Rule::token('open', '['));
        $grammar->global->add(Rule::token('close', ']'));
        $grammar->global->withRootSequence('(open close)');

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

    #[Test]
    public function shouldMatchGroupWithOneOrMoreCardinality(): void
    {
        $grammar = new Grammar('grouping-test');
        $grammar->global->add(Rule::token('open', '['));
        $grammar->global->add(Rule::token('close', ']'));
        $grammar->global->withRootSequence('(open close)+');

        $this->assertGrammarParsing(
            string: '[][]',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $attributes = $node->getAttributes();

                $test->assertCount(4, $attributes);
                $test->assertSame('[', $attributes[0]->content);
                $test->assertSame(']', $attributes[1]->content);
                $test->assertSame('[', $attributes[2]->content);
                $test->assertSame(']', $attributes[3]->content);
            },
            requireBofEof: false,
        );
    }

    #[Test]
    public function shouldMatchFirstAlternativeGroup(): void
    {
        $grammar = new Grammar('grouping-test');
        $grammar->global->add(Rule::token('open', '['));
        $grammar->global->add(Rule::token('close', ']'));
        $grammar->global->add(Rule::expr('value', '[a-z]+'));
        $grammar->global->withRootSequence('(open close)|(open value close)');

        $this->assertGrammarParsing(
            string: '[]',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $attributes = $node->getAttributes();

                $test->assertCount(2, $attributes);
                $test->assertSame('[', $attributes[0]->content);
                $test->assertSame(']', $attributes[1]->content);
            },
            requireBofEof: false,
        );
    }

    #[Test]
    public function shouldMatchSecondAlternativeGroup(): void
    {
        $grammar = new Grammar('grouping-test');
        $grammar->global->add(Rule::token('open', '['));
        $grammar->global->add(Rule::token('close', ']'));
        $grammar->global->add(Rule::expr('value', '[a-z]+'));
        $grammar->global->withRootSequence('(open close)|(open value close)');

        $this->assertGrammarParsing(
            string: '[abc]',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $attributes = $node->getAttributes();

                $test->assertCount(3, $attributes);
                $test->assertSame('[', $attributes[0]->content);
                $test->assertSame('abc', $attributes[1]->content);
                $test->assertSame(']', $attributes[2]->content);
            },
            requireBofEof: false,
        );
    }

    #[Test]
    public function shouldCastNodeToStringForGroup(): void
    {
        $grammar = new Grammar('grouping-test');
        $grammar->global->add(Rule::token('open', '['));
        $grammar->global->add(Rule::token('close', ']'));
        $grammar->global->withRootSequence('(open close)+');

        $this->assertGrammarParsing(
            string: '[][]',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $test->assertSame('[][]', (string) $node);
            },
            requireBofEof: false,
        );
    }
}
