<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Func\Foundation\Grammar\Rule;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\Token;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\TokenRegion;
use PhpArchitecture\Parser\Tests\Func\Grammar\GrammarTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('func')]
final class PriorityRuleTest extends GrammarTestCase
{
    // --- Tokenization ---

    #[Test]
    public function shouldHigherPriorityRuleWinOverLowerPriority(): void
    {
        // "null" matches both: keyword (priority 1) and identifier [a-z]+ (priority 0)
        // Higher priority wins regardless of match length being equal.
        $grammar = new Grammar('priority-test');
        $grammar->global->add(Rule::keyword('null')->priority(1));
        $grammar->global->add(Rule::expr('identifier', '[a-z]+')->priority(0));

        $this->assertGrammarParsing(
            string: 'null',
            grammar: $grammar,
            assertTokenizationResultValid: function (TokenRegion $region, self $test): void {
                $tokens = $test->getContentTokens($region);

                $test->assertCount(1, $tokens);
                $test->assertSame('null', $tokens[0]->name);
            },
        );
    }

    #[Test]
    public function shouldLongerMatchWinWhenSamePriority(): void
    {
        // Both rules at priority 0, but [a-z]+ matches "hello" (5 chars) vs [a-z] matching "h" (1 char).
        // Longer match wins.
        $grammar = new Grammar('priority-test');
        $grammar->global->add(Rule::expr('single', '[a-z]'));
        $grammar->global->add(Rule::expr('word', '[a-z]+'));

        $this->assertGrammarParsing(
            string: 'hello',
            grammar: $grammar,
            assertTokenizationResultValid: function (TokenRegion $region, self $test): void {
                $tokens = $test->getContentTokens($region);

                $test->assertCount(1, $tokens);
                $test->assertSame('word', $tokens[0]->name);
                $test->assertSame('hello', $tokens[0]->raw);
            },
        );
    }

    #[Test]
    public function shouldFirstDefinedRuleWinWhenSamePriorityAndSameLength(): void
    {
        // Both rules match 'x' with length 1 at priority 0.
        // First defined (ruleA) wins based on insertion order.
        $grammar = new Grammar('priority-test');
        $grammar->global->add(Rule::token('ruleA', 'x'));
        $grammar->global->add(Rule::token('ruleB', 'x'));

        $this->assertGrammarParsing(
            string: 'x',
            grammar: $grammar,
            assertTokenizationResultValid: function (TokenRegion $region, self $test): void {
                $tokens = $test->getContentTokens($region);

                $test->assertCount(1, $tokens);
                $test->assertSame('ruleA', $tokens[0]->name);
            },
        );
    }

    // --- Parsing result (NodeInterface) ---

    #[Test]
    public function shouldCastNodeToStringAsInputRegardlessOfWinner(): void
    {
        $grammar = new Grammar('priority-test');
        $grammar->global->add(Rule::keyword('null')->priority(1));
        $grammar->global->add(Rule::expr('identifier', '[a-z]+')->priority(0));

        $this->assertGrammarParsing(
            string: 'null',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $test->assertSame('null', (string) $node);
            },
        );
    }

    #[Test]
    public function shouldNodeNameReflectWinningRule(): void
    {
        $grammar = new Grammar('priority-test');
        $grammar->global->add(Rule::keyword('null')->priority(1));
        $grammar->global->add(Rule::expr('identifier', '[a-z]+')->priority(0));

        $this->assertGrammarParsing(
            string: 'null',
            grammar: $grammar,
            assertTokenizationResultValid: function (TokenRegion $region, self $test): void {
                $tokens = $test->getContentTokens($region);
                $test->assertSame('null', $tokens[0]->name);
            },
        );
    }

    // --- Helpers ---

    /** @return Token[] */
    private function getContentTokens(TokenRegion $region): array
    {
        return array_values(
            array_filter(
                $region->stream->tokens,
                static fn(Token|TokenRegion $t): bool => $t instanceof Token
                    && $t->name !== Token::TOKEN_BOF
                    && $t->name !== Token::TOKEN_EOF,
            ),
        );
    }
}
