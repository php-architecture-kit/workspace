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
final class SequenceLookaheadLookbehindTest extends GrammarTestCase
{
    // --- Lookahead ---

    #[Test]
    public function shouldLookaheadNotConsumeToken(): void
    {
        // Sequence: (open >close) close
        // The nested group matches 'open' and verifies 'close' follows (lookahead),
        // without consuming 'close'. The outer 'close' node then consumes it.
        // If lookahead consumed 'close', the outer match would fail.
        $grammar = new Grammar('lookahead-test');
        $grammar->global->add(Rule::token('open', '['));
        $grammar->global->add(Rule::token('close', ']'));
        $grammar->global->withRootSequence('(open >close) close');

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
    public function shouldLookaheadProduceNoAttribute(): void
    {
        // Lookahead node must not appear as an attribute in the parse tree.
        // The sequence (open >close) produces only 'open' attribute, not 'close'.
        // The outer 'close' then adds a second attribute — total must be exactly 2.
        $grammar = new Grammar('lookahead-test');
        $grammar->global->add(Rule::token('open', '['));
        $grammar->global->add(Rule::token('close', ']'));
        $grammar->global->withRootSequence('(open >close) close');

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

    // --- Lookbehind ---

    #[Test]
    public function shouldLookbehindNotConsumeToken(): void
    {
        // Sequence: open (<open close)
        // After 'open' is consumed, the nested group checks the previous token
        // was 'open' (lookbehind), then matches 'close'.
        // If lookbehind consumed 'open' again, 'close' matching would misalign.
        $grammar = new Grammar('lookbehind-test');
        $grammar->global->add(Rule::token('open', '['));
        $grammar->global->add(Rule::token('close', ']'));
        $grammar->global->withRootSequence('open (<open close)');

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
    public function shouldLookbehindProduceNoAttribute(): void
    {
        // Lookbehind node must not appear as an attribute in the parse tree.
        // open (<open close) on '[]' → 2 attributes total (open + close).
        $grammar = new Grammar('lookbehind-test');
        $grammar->global->add(Rule::token('open', '['));
        $grammar->global->add(Rule::token('close', ']'));
        $grammar->global->withRootSequence('open (<open close)');

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

    // --- BOF / EOF (Rule::technical) ---

    #[Test]
    public function shouldLookaheadMatchEofToken(): void
    {
        // Canonical use: verify the matched value is the last token before EOF.
        // Sequence: bof (value >eof) eof
        // BOF is consumed at the outer level; the nested group matches 'value'
        // and peeks ahead to verify EOF follows (without consuming it);
        // the outer 'eof' then consumes EOF.
        $grammar = new Grammar('lookahead-eof-test');
        $grammar->global->add(Rule::technical('bof'));
        $grammar->global->add(Rule::technical('eof'));
        $grammar->global->add(Rule::token('value', 'x'));
        $grammar->global->withRootSequence('bof (value >eof) eof');

        $this->assertGrammarParsing(
            string: 'x',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $test->assertSame('x', (string) $node);
            },
            requireBofEof: true,
        );
    }

    #[Test]
    public function shouldLookbehindMatchBofToken(): void
    {
        // Canonical use: verify the matched value is the first token after BOF.
        // Sequence: bof (<bof value) eof
        // BOF is consumed at the outer level; the nested group checks the previous
        // token was BOF (lookbehind) and then matches 'value'; EOF is consumed last.
        $grammar = new Grammar('lookbehind-bof-test');
        $grammar->global->add(Rule::technical('bof'));
        $grammar->global->add(Rule::technical('eof'));
        $grammar->global->add(Rule::token('value', 'x'));
        $grammar->global->withRootSequence('bof (<bof value) eof');

        $this->assertGrammarParsing(
            string: 'x',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $test->assertSame('x', (string) $node);
            },
            requireBofEof: true,
        );
    }
}
