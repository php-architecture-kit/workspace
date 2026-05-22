<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Func\Foundation\Grammar\Rule;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Region;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\Token;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\TokenRegion;
use PhpArchitecture\Parser\Tests\Func\Grammar\GrammarTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('func')]
final class RegionTest extends GrammarTestCase
{
    #[Test]
    public function shouldEnterRegionWhenOpenerMatches(): void
    {
        $grammar = new Grammar('region-test');

        $inner = (new Region('inner'))
            ->openWith(Rule::token('open', '['), includeOpenRuleMatch: true)
            ->closeWith(Rule::token('close', ']'), includeCloseRuleMatch: true);

        $inner->add(Rule::expr('content', '[a-z]+'));
        $grammar->global->add($inner);

        $this->assertGrammarParsing(
            string: '[abc]',
            grammar: $grammar,
            assertTokenizationResultValid: function (TokenRegion $tokenRegion, self $test): void {
                $tokens = $tokenRegion->stream->tokens;

                $test->assertCount(1, $tokens);
                $test->assertInstanceOf(TokenRegion::class, $tokens[0]);
                $test->assertSame('inner', $tokens[0]->name);
            },
            requireBofEof: false,
        );
    }

    #[Test]
    public function shouldExitRegionWhenCloserMatches(): void
    {
        $grammar = new Grammar('region-test');
        $grammar->global->add(Rule::token('other', 'x'));

        $inner = (new Region('inner'))
            ->openWith(Rule::token('open', '['), includeOpenRuleMatch: true)
            ->closeWith(Rule::token('close', ']'), includeCloseRuleMatch: true);

        $inner->add(Rule::expr('content', '[a-z]+'));
        $grammar->global->add($inner);

        $this->assertGrammarParsing(
            string: '[abc]x',
            grammar: $grammar,
            assertTokenizationResultValid: function (TokenRegion $tokenRegion, self $test): void {
                $tokens = $tokenRegion->stream->tokens;

                // Region is closed before 'x', so root stream has: inner_region + other_token
                $test->assertCount(2, $tokens);
                $test->assertInstanceOf(TokenRegion::class, $tokens[0]);
                $test->assertInstanceOf(Token::class, $tokens[1]);
                $test->assertSame('other', $tokens[1]->name);
            },
            requireBofEof: false,
        );
    }

    #[Test]
    public function shouldIncludeOpenerTokenInRegionWhenIncludeMatchTrue(): void
    {
        $grammar = new Grammar('region-test');

        $inner = (new Region('inner'))
            ->openWith(Rule::token('open', '['), includeOpenRuleMatch: true)
            ->closeWith(Rule::token('close', ']'), includeCloseRuleMatch: true);

        $grammar->global->add($inner);

        $this->assertGrammarParsing(
            string: '[]',
            grammar: $grammar,
            assertTokenizationResultValid: function (TokenRegion $tokenRegion, self $test): void {
                $innerRegion = $tokenRegion->stream->tokens[0];
                $test->assertInstanceOf(TokenRegion::class, $innerRegion);

                $innerTokens = $innerRegion->stream->tokens;
                $test->assertSame('open', $innerTokens[0]->name);
            },
            requireBofEof: false,
        );
    }

    #[Test]
    public function shouldExcludeOpenerTokenFromRegionWhenIncludeMatchFalse(): void
    {
        // When includeOpenRuleMatch=false, the opener rule must also be defined in the parent
        // region so it can be tokenized. The opener token then stays in the parent stream,
        // and the inner region starts after the token is added.
        $grammar = new Grammar('region-test');
        $grammar->global->add(Rule::token('open', '['));

        $inner = (new Region('inner'))
            ->openWith(Rule::token('open', '['), includeOpenRuleMatch: false)
            ->closeWith(Rule::token('close', ']'), includeCloseRuleMatch: true);

        $grammar->global->add($inner);

        $this->assertGrammarParsing(
            string: '[]',
            grammar: $grammar,
            assertTokenizationResultValid: function (TokenRegion $tokenRegion, self $test): void {
                $tokens = $tokenRegion->stream->tokens;

                // The opener token stays in the parent stream, inner region follows
                $test->assertCount(2, $tokens);
                $test->assertInstanceOf(Token::class, $tokens[0]);
                $test->assertSame('open', $tokens[0]->name);
                $test->assertInstanceOf(TokenRegion::class, $tokens[1]);
            },
            requireBofEof: false,
        );
    }

    #[Test]
    public function shouldCastNodeToStringAsFullInput(): void
    {
        $grammar = new Grammar('region-test');

        $inner = (new Region('inner'))
            ->openWith(Rule::token('open', '['), includeOpenRuleMatch: true)
            ->closeWith(Rule::token('close', ']'), includeCloseRuleMatch: true);

        $inner->add(Rule::expr('content', '[a-z]+'));
        $grammar->global->add($inner);

        $this->assertGrammarParsing(
            string: '[hello]',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $test->assertSame('[hello]', (string) $node);
            },
            requireBofEof: false,
        );
    }
}
