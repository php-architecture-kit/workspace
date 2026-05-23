<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Func\Foundation\Grammar\Definition\Sequence;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Tests\Func\Grammar\GrammarTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('func')]
final class SequenceNegationTest extends GrammarTestCase
{
    #[Test]
    public function shouldConsumeTokenThatIsNotTheNegatedOne(): void
    {
        // Sequence: open !close close
        // Input '[x]': '[' → open, 'x' is not ']' → !close passes and consumes 'x', ']' → close
        $grammar = new Grammar('negation-test');
        $grammar->global->add(Rule::token('open', '['));
        $grammar->global->add(Rule::token('close', ']'));
        $grammar->global->add(Rule::token('other', 'x'));
        $grammar->global->withRootSequence('open !close close');

        $this->assertGrammarParsing(
            string: '[x]',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $test->assertSame('[x]', (string) $node);
            },
            requireBofEof: false,
        );
    }

    #[Test]
    public function shouldConsumeMultipleNonNegatedTokensWithPlus(): void
    {
        // Sequence: open !close+ close
        // Input '[xxx]': '[' → open, 'x','x','x' all not ']' → !close+ consumes all, ']' → close
        $grammar = new Grammar('negation-plus-test');
        $grammar->global->add(Rule::token('open', '['));
        $grammar->global->add(Rule::token('close', ']'));
        $grammar->global->add(Rule::token('x', 'x'));
        $grammar->global->withRootSequence('open !close+ close');

        $this->assertGrammarParsing(
            string: '[xxx]',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $test->assertSame('[xxx]', (string) $node);
            },
            requireBofEof: false,
        );
    }

    #[Test]
    public function shouldAllowNegationAsFirstNodeInSequence(): void
    {
        // Sequence: !close open close
        // Input '[x]': first token '[' is not ']' → !close passes, '[' → open, ']' → close
        // Wait - !close consumes the current token that is not close.
        // So: '['(not close)→consumed by !close, but then 'open' needs '[' which is gone.
        // Correct scenario: first '!' is NOT close → consumes the token, so 'open' needs '[' → already consumed → FAIL
        // Let's use: !x open close
        // Input '[x]' would: '[' is not 'x' → !x consumes '[', then open needs '[' → gone → FAIL
        // Better: use input 'x[]': 'x' is not '['... hmm this gets complex.
        // Simplest: standalone sequence where first node is negation:
        // Sequence: !close
        // Input: '[' → '[' is not ']' → negation passes, consumes '[' → matched
        $grammar = new Grammar('negation-first-test');
        $grammar->global->add(Rule::token('open', '['));
        $grammar->global->add(Rule::token('close', ']'));
        $grammar->global->withRootSequence('!close');

        $this->assertGrammarParsing(
            string: '[',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $test->assertSame('[', (string) $node);
            },
            requireBofEof: false,
        );
    }

    #[Test]
    public function shouldNegationInMiddleOfSequenceNotConsumeNegatedToken(): void
    {
        // Sequence: open !close close
        // Specifically verifies that the negated token (close=']') is NOT consumed by !close.
        // If it were consumed, the final 'close' node would have nothing to match.
        // Input '[a]' (where 'a' is not defined, so it would be 'unknown'... use 'x').
        // Sequence: open !close close on '[x]' → open consumes '[', !close consumes 'x',
        // close must still see ']' (not consumed by !close) → success
        $grammar = new Grammar('negation-middle-verify-test');
        $grammar->global->add(Rule::token('open', '['));
        $grammar->global->add(Rule::token('close', ']'));
        $grammar->global->add(Rule::token('x', 'x'));
        $grammar->global->withRootSequence('open !close close');

        $this->assertGrammarParsing(
            string: '[x]',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $test->assertSame('[x]', (string) $node);
            },
            requireBofEof: false,
        );
    }
}
