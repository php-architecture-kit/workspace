<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Func\Foundation\Grammar\Rule;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\RawContentAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Node;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\Token;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\TokenRegion;
use PhpArchitecture\Parser\Tests\Func\Grammar\GrammarTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('func')]
final class TokenRuleTest extends GrammarTestCase
{
    // --- Tokenization ---

    #[Test]
    public function shouldProduceExpectedTokenNameAndRaw(): void
    {
        $grammar = new Grammar('token-test');
        $grammar->global->add(Rule::token('open', '['));

        $this->assertGrammarParsing(
            string: '[',
            grammar: $grammar,
            assertTokenizationResultValid: function (TokenRegion $region, self $test): void {
                $tokens = $test->getContentTokens($region);

                $test->assertCount(1, $tokens);
                $test->assertSame('open', $tokens[0]->name);
                $test->assertSame('[', $tokens[0]->raw);
            },
        );
    }

    #[Test]
    public function shouldProduceBofAndEofAroundMatchedToken(): void
    {
        $grammar = new Grammar('token-test');
        $grammar->global->add(Rule::token('open', '['));

        $this->assertGrammarParsing(
            string: '[',
            grammar: $grammar,
            assertTokenizationResultValid: function (TokenRegion $region, self $test): void {
                $tokens = array_values($region->stream->tokens);

                $test->assertSame(Token::TOKEN_BOF, $tokens[0]->name);
                $test->assertSame(Token::TOKEN_EOF, $tokens[2]->name);
            },
        );
    }

    #[Test]
    public function shouldProduceUnknownTokenForUnrecognizedCharacter(): void
    {
        $grammar = new Grammar('token-test');
        $grammar->global->add(Rule::token('open', '['));

        $this->assertGrammarParsing(
            string: 'x',
            grammar: $grammar,
            assertTokenizationResultValid: function (TokenRegion $region, self $test): void {
                $tokens = $test->getContentTokens($region);

                $test->assertCount(1, $tokens);
                $test->assertSame(Token::TOKEN_UNKNOWN, $tokens[0]->name);
                $test->assertSame('x', $tokens[0]->raw);
            },
        );
    }

    #[Test]
    public function shouldTokenizeConsecutiveMatchesAsIndividualTokens(): void
    {
        $grammar = new Grammar('token-test');
        $grammar->global->add(Rule::token('open', '['));

        $this->assertGrammarParsing(
            string: '[[',
            grammar: $grammar,
            assertTokenizationResultValid: function (TokenRegion $region, self $test): void {
                $tokens = $test->getContentTokens($region);

                $test->assertCount(2, $tokens);
                $test->assertSame('open', $tokens[0]->name);
                $test->assertSame('open', $tokens[1]->name);
            },
        );
    }

    #[Test]
    public function shouldProduceUnknownBeforeMatchedTokenWhenPrecedingCharacterNotRecognized(): void
    {
        $grammar = new Grammar('token-test');
        $grammar->global->add(Rule::token('open', '['));

        $this->assertGrammarParsing(
            string: 'a[',
            grammar: $grammar,
            assertTokenizationResultValid: function (TokenRegion $region, self $test): void {
                $tokens = $test->getContentTokens($region);

                $test->assertCount(2, $tokens);
                $test->assertSame(Token::TOKEN_UNKNOWN, $tokens[0]->name);
                $test->assertSame('open', $tokens[1]->name);
            },
        );
    }

    // --- Parsing result (NodeInterface) ---

    #[Test]
    public function shouldReturnNodeInstance(): void
    {
        $grammar = new Grammar('token-test');
        $grammar->global->add(Rule::token('open', '['));

        $this->assertGrammarParsing(
            string: '[',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $test->assertInstanceOf(Node::class, $node);
            },
        );
    }

    #[Test]
    public function shouldCastNodeToStringAsMatchedInput(): void
    {
        $grammar = new Grammar('token-test');
        $grammar->global->add(Rule::token('open', '['));

        $this->assertGrammarParsing(
            string: '[',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $test->assertSame('[', (string) $node);
            },
        );
    }

    #[Test]
    public function shouldProduceSingleRawContentAttributeExcludingBofAndEof(): void
    {
        // bof and eof carry NodeType::Skip tag, so they are not turned into attributes.
        // Input "[" → stream: [bof(skip), open, eof(skip)] → 1 attribute.
        $grammar = new Grammar('token-test');
        $grammar->global->add(Rule::token('open', '['));

        $this->assertGrammarParsing(
            string: '[',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $attributes = $node->getAttributes();

                $test->assertCount(1, $attributes);
                $test->assertInstanceOf(RawContentAttribute::class, $attributes[0]);
            },
        );
    }

    #[Test]
    public function shouldIncludeUnknownTokensInRawContent(): void
    {
        $grammar = new Grammar('token-test');
        $grammar->global->add(Rule::token('open', '['));

        $this->assertGrammarParsing(
            string: 'a[',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $test->assertSame('a[', (string) $node);
            },
        );
    }

    #[Test]
    public function shouldCastMultipleConsecutiveMatchesToString(): void
    {
        $grammar = new Grammar('token-test');
        $grammar->global->add(Rule::token('open', '['));

        $this->assertGrammarParsing(
            string: '[[',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $test->assertSame('[[', (string) $node);
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
