<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Func\Foundation\Grammar\Rule;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Region;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\Token;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\TokenRegion;
use PhpArchitecture\Parser\Tests\Func\Grammar\GrammarTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('func')]
final class InnerGrammarTest extends GrammarTestCase
{
    #[Test]
    public function shouldMergeInnerGrammarRulesIntoRegion(): void
    {
        // withMergedInnerGrammar merges the inner grammar's root region rules into the
        // target region at compile time — those rules are then available for tokenization
        // inside that region without being defined there directly.
        $innerGrammar = new Grammar('special-grammar');
        $innerGrammar->global->add(Rule::token('special', 'e'));

        $outerGrammar = new Grammar('outer-grammar');

        $inner = (new Region('inner'))
            ->openWith(Rule::token('open', '['), includeOpenRuleMatch: true)
            ->closeWith(Rule::token('close', ']'), includeCloseRuleMatch: true);
        $inner->withMergedInnerGrammar($innerGrammar);

        $outerGrammar->global->add($inner);

        $this->assertGrammarParsing(
            string: '[e]',
            grammar: $outerGrammar,
            assertTokenizationResultValid: function (TokenRegion $tokenRegion, self $test): void {
                $innerRegion = $tokenRegion->stream->tokens[0];
                $test->assertInstanceOf(TokenRegion::class, $innerRegion);

                $innerTokens = $innerRegion->stream->tokens;
                // open('[') + special('e') + close(']')
                $test->assertCount(3, $innerTokens);
                $test->assertSame('open', $innerTokens[0]->name);
                $test->assertSame('special', $innerTokens[1]->name);
                $test->assertSame('e', $innerTokens[1]->raw);
                $test->assertSame('close', $innerTokens[2]->name);
            },
            requireBofEof: false,
        );
    }

    #[Test]
    public function shouldRetokenizeRegionWithInnerGrammar(): void
    {
        // retokenizedByInnerGrammar re-tokenizes the region's content (as a string) using
        // the inner grammar after the region closes. The original token stream is replaced.
        $csvGrammar = new Grammar('csv');
        $csvGrammar->requireBofEof = false;
        $csvGrammar->global->add(Rule::expr('word', '[a-zA-Z]+'));
        $csvGrammar->global->add(Rule::token('comma', ','));

        $outerGrammar = new Grammar('outer');
        $outerGrammar->global->add(Rule::token('open', '['));
        $outerGrammar->global->add(Rule::token('close', ']'));

        $inner = (new Region('inner'))
            ->openWith(Rule::token('open', '['), includeOpenRuleMatch: false)
            ->closeWith(Rule::token('close', ']'), includeCloseRuleMatch: false);
        $inner->add(Rule::expr('raw', '[^\]]+'));
        $inner->retokenizedByInnerGrammar($csvGrammar);

        $outerGrammar->global->add($inner);

        $this->assertGrammarParsing(
            string: '[abc,def]',
            grammar: $outerGrammar,
            assertTokenizationResultValid: function (TokenRegion $tokenRegion, self $test): void {
                $tokens = $tokenRegion->stream->tokens;
                // parent: open('[') + inner_region + close(']')
                $test->assertCount(3, $tokens);
                $test->assertInstanceOf(Token::class, $tokens[0]);
                $test->assertSame('open', $tokens[0]->name);
                $test->assertInstanceOf(TokenRegion::class, $tokens[1]);
                $test->assertSame('inner', $tokens[1]->name);
                $test->assertInstanceOf(Token::class, $tokens[2]);
                $test->assertSame('close', $tokens[2]->name);

                // inner after retokenization: word + comma + word
                $innerTokens = $tokens[1]->stream->tokens;
                $test->assertCount(3, $innerTokens);
                $test->assertSame('word', $innerTokens[0]->name);
                $test->assertSame('abc', $innerTokens[0]->raw);
                $test->assertSame('comma', $innerTokens[1]->name);
                $test->assertSame(',', $innerTokens[1]->raw);
                $test->assertSame('word', $innerTokens[2]->name);
                $test->assertSame('def', $innerTokens[2]->raw);
            },
            requireBofEof: false,
        );
    }
}
