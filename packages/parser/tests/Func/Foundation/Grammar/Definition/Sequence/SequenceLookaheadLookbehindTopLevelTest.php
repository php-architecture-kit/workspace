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

/**
 * Tests for lookahead/lookbehind at the TOP LEVEL of a named sequence (not inside a nested group).
 *
 * Existing tests in SequenceLookaheadLookbehindTest cover lookahead/lookbehind *inside* a nested
 * group like "(open >close)", which goes through matchNestedSequence (correctly implemented).
 *
 * The bug is in matchSequence: it does NOT handle isLookahead/isLookbehind on top-level nodes,
 * so a sequence like "a >b" (where >b is a top-level SequenceNode) incorrectly consumes 'b'.
 *
 * Two contexts are tested:
 *   1. Named sequence with top-level lookahead called by the root sequence (processWithRoot path).
 *   2. Named sequence with top-level lookahead used in a region without root sequence
 *      (processWithoutRoot path).
 */
#[Group('func')]
final class SequenceLookaheadLookbehindTopLevelTest extends GrammarTestCase
{
    // -------------------------------------------------------------------------
    // Case 1: named sequence with top-level lookahead, called via root sequence
    // -------------------------------------------------------------------------

    #[Test]
    public function shouldTopLevelLookaheadInNamedSequenceNotConsumeToken(): void
    {
        // Named sequence 'sub': a >b
        //   - matches 'a' and looks ahead to verify 'b' follows, without consuming 'b'
        // Root sequence: sub b
        //   - calls sub (should advance offset to 1), then expects 'b' at offset 1
        //
        // Bug: matchSequence ignores isLookahead on >b, so sub consumes 'b' (offset→2).
        // Root sequence then fails to find 'b' at offset 2 → processWithRoot throws.
        $grammar = new Grammar('top-level-lookahead-via-root');
        $grammar->global->add(Rule::token('a', 'a'));
        $grammar->global->add(Rule::token('b', 'b'));
        $grammar->global->add(Rule::seq('sub', 'a >b'));
        $grammar->global->withRootSequence('sub b');

        $this->assertGrammarParsing(
            string: 'ab',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $test->assertSame('ab', (string) $node);
            },
            requireBofEof: false,
        );
    }

    #[Test]
    public function shouldTopLevelLookaheadInNamedSequenceProduceNoAttribute(): void
    {
        // Named sequence 'sub': a >b
        //   - lookahead >b must NOT appear as an attribute inside sub's node
        // Root sequence: sub b
        //   - root node must have 2 attributes: NodeAttribute(sub) + RawContentAttribute('b')
        //   - sub node must have exactly 1 attribute: RawContentAttribute('a')
        //
        // Bug: sub's MatchedSequence includes 'b' as a second attribute (consumed, not peeked).
        $grammar = new Grammar('top-level-lookahead-attribute-via-root');
        $grammar->global->add(Rule::token('a', 'a'));
        $grammar->global->add(Rule::token('b', 'b'));
        $grammar->global->add(Rule::seq('sub', 'a >b'));
        $grammar->global->withRootSequence('sub b');

        $this->assertGrammarParsing(
            string: 'ab',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $rootAttributes = $node->getAttributes();
                $test->assertCount(2, $rootAttributes);

                // sub's child node must contain only 'a' — not 'b'
                $subNode = $rootAttributes[0]->node;
                $subAttributes = $subNode->getAttributes();
                $test->assertCount(1, $subAttributes);
                $test->assertInstanceOf(RawContentAttribute::class, $subAttributes[0]);
                $test->assertSame('a', $subAttributes[0]->content);
            },
            requireBofEof: false,
        );
    }

    // -------------------------------------------------------------------------
    // Case 2: named sequence with top-level lookahead, region without root sequence
    // -------------------------------------------------------------------------

    #[Test]
    public function shouldTopLevelLookaheadInSequenceNotConsumeTokenWithoutRootSequence(): void
    {
        // No root sequence → processWithoutRoot iterates named sequences in insertion order.
        //
        // Named sequence 'full' (tried first): sub b c
        //   - calls sub, then matches 'b', then matches 'c'
        // Named sequence 'sub' (tried second): a >b
        //   - matches 'a', lookahead >b (must NOT consume 'b')
        //
        // Correct behaviour: full matches 'abc' entirely — nothing is unmatched.
        // Bug: matchSequence ignores >b, sub consumes 'b' (offset→2). full then tries to
        //   match 'b' at offset 2 where 'c' is — fails. full is abandoned. sub matches 'ab'
        //   directly (bug). 'c' is left unmatched → it appears as an attribute on the node.
        $grammar = new Grammar('top-level-lookahead-without-root');
        $grammar->global->add(Rule::token('a', 'a'));
        $grammar->global->add(Rule::token('b', 'b'));
        $grammar->global->add(Rule::token('c', 'c'));
        $grammar->global->add(Rule::seq('full', 'sub b c')->priority(1));  // higher priority → tried first
        $grammar->global->add(Rule::seq('sub', 'a >b'));                  // lower priority → tried second

        $this->assertGrammarParsing(
            string: 'abc',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                // 'full' consumed all tokens — MatchedRegion has no unmatched tokens,
                // so the RawContentAttribute content is empty.
                // Bug: 'full' fails (sub erroneously consumes 'b'), sub consumes 'ab',
                // leaving 'c' unmatched → (string) $node === 'c'.
                $test->assertSame('', (string) $node);
            },
            requireBofEof: false,
        );
    }

    #[Test]
    public function shouldTopLevelLookbehindInNamedSequenceNotConsumeToken(): void
    {
        // Named sequence 'sub': <a b
        //   - looks behind at 'a' (already consumed by root), then matches 'b'
        //   - lookbehind must NOT re-consume 'a'; it only verifies the previous token
        // Root sequence: a sub
        //   - root matches 'a', then calls sub (sub should see 'a' as lookbehind and match 'b')
        //
        // Bug: matchSequence ignores isLookbehind on <a, so offset is not decremented before
        //   matching — sub tries to match 'a' at the current offset (which is 'b') → fails,
        //   OR sub decrement is never done so the lookbehind check fails against 'b'.
        $grammar = new Grammar('top-level-lookbehind-via-root');
        $grammar->global->add(Rule::token('a', 'a'));
        $grammar->global->add(Rule::token('b', 'b'));
        $grammar->global->add(Rule::seq('sub', '<a b'));
        $grammar->global->withRootSequence('a sub');

        $this->assertGrammarParsing(
            string: 'ab',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $test->assertSame('ab', (string) $node);
            },
            requireBofEof: false,
        );
    }

    #[Test]
    public function shouldTopLevelLookbehindInNamedSequenceProduceNoAttribute(): void
    {
        // Named sequence 'sub': <a b
        //   - lookbehind <a must NOT appear as an attribute inside sub's node
        // Root sequence: a sub
        //   - root node: 2 attributes — RawContentAttribute('a') + NodeAttribute(sub)
        //   - sub node: 1 attribute — RawContentAttribute('b') only
        //
        // Bug: <a is consumed as a regular match → sub's node has 2 attributes ('a' and 'b').
        $grammar = new Grammar('top-level-lookbehind-attribute-via-root');
        $grammar->global->add(Rule::token('a', 'a'));
        $grammar->global->add(Rule::token('b', 'b'));
        $grammar->global->add(Rule::seq('sub', '<a b'));
        $grammar->global->withRootSequence('a sub');

        $this->assertGrammarParsing(
            string: 'ab',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $rootAttributes = $node->getAttributes();
                $test->assertCount(2, $rootAttributes);

                // sub's child node must contain only 'b' — not 'a' (lookbehind must not produce attribute)
                $subNode = $rootAttributes[1]->node;
                $subAttributes = $subNode->getAttributes();
                $test->assertCount(1, $subAttributes);
                $test->assertInstanceOf(RawContentAttribute::class, $subAttributes[0]);
                $test->assertSame('b', $subAttributes[0]->content);
            },
            requireBofEof: false,
        );
    }
}
