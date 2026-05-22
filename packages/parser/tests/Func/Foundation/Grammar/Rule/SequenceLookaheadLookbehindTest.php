<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Func\Foundation\Grammar\Rule;

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
}
