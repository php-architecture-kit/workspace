<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Func\Foundation\Grammar\Rule;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\RawContentAttribute;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\Token;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\TokenRegion;
use PhpArchitecture\Parser\Tests\Func\Grammar\GrammarTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('func')]
final class KeywordRuleTest extends GrammarTestCase
{
    // --- Tokenization ---

    #[Test]
    public function shouldMatchKeywordCaseInsensitivelyByDefault(): void
    {
        $grammar = new Grammar('keyword-test');
        $grammar->global->add(Rule::keyword('null'));

        $this->assertGrammarParsing(
            string: 'NULL',
            grammar: $grammar,
            assertTokenizationResultValid: function (TokenRegion $region, self $test): void {
                $tokens = $test->getContentTokens($region);

                $test->assertCount(1, $tokens);
                $test->assertSame('null', $tokens[0]->name);
                $test->assertSame('NULL', $tokens[0]->raw);
            },
        );
    }

    #[Test]
    public function shouldMatchKeywordWithLowercaseInput(): void
    {
        $grammar = new Grammar('keyword-test');
        $grammar->global->add(Rule::keyword('null'));

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
    public function shouldProduceOnlyUnknownsWhenCaseSensitiveAndCaseMismatch(): void
    {
        $grammar = new Grammar('keyword-test');
        $grammar->global->add(Rule::keyword('null', caseSensitive: true));

        $this->assertGrammarParsing(
            string: 'NULL',
            grammar: $grammar,
            assertTokenizationResultValid: function (TokenRegion $region, self $test): void {
                $tokens = $test->getContentTokens($region);

                $test->assertNotEmpty($tokens);
                foreach ($tokens as $token) {
                    $test->assertSame(Token::TOKEN_UNKNOWN, $token->name);
                }
            },
        );
    }

    #[Test]
    public function shouldMatchWhenCaseSensitiveAndCaseMatches(): void
    {
        $grammar = new Grammar('keyword-test');
        $grammar->global->add(Rule::keyword('null', caseSensitive: true));

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
    public function shouldUseKeywordStringAsDefaultTokenName(): void
    {
        $grammar = new Grammar('keyword-test');
        $grammar->global->add(Rule::keyword('null'));

        $this->assertGrammarParsing(
            string: 'null',
            grammar: $grammar,
            assertTokenizationResultValid: function (TokenRegion $region, self $test): void {
                $tokens = $test->getContentTokens($region);

                $test->assertSame('null', $tokens[0]->name);
            },
        );
    }

    #[Test]
    public function shouldUseCustomNameWhenProvided(): void
    {
        $grammar = new Grammar('keyword-test');
        $grammar->global->add(Rule::keyword('null', name: 'nullKw'));

        $this->assertGrammarParsing(
            string: 'null',
            grammar: $grammar,
            assertTokenizationResultValid: function (TokenRegion $region, self $test): void {
                $tokens = $test->getContentTokens($region);

                $test->assertSame('nullKw', $tokens[0]->name);
            },
        );
    }

    // --- Parsing result (NodeInterface) ---

    #[Test]
    public function shouldCastNodeToStringAsMatchedInput(): void
    {
        $grammar = new Grammar('keyword-test');
        $grammar->global->add(Rule::keyword('null'));

        $this->assertGrammarParsing(
            string: 'NULL',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $test->assertSame('NULL', (string) $node);
            },
        );
    }

    #[Test]
    public function shouldProduceSingleRawContentAttributeByDefault(): void
    {
        $grammar = new Grammar('keyword-test');
        $grammar->global->add(Rule::keyword('null'));

        $this->assertGrammarParsing(
            string: 'null',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $attributes = $node->getAttributes();

                $test->assertCount(1, $attributes);
                $test->assertInstanceOf(RawContentAttribute::class, $attributes[0]);
            },
        );
    }

    #[Test]
    public function shouldRawContentAttributePreserveOriginalCase(): void
    {
        $grammar = new Grammar('keyword-test');
        $grammar->global->add(Rule::keyword('null'));

        $this->assertGrammarParsing(
            string: 'NULL',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $attributes = $node->getAttributes();

                $test->assertInstanceOf(RawContentAttribute::class, $attributes[0]);
                $test->assertSame('NULL', $attributes[0]->content);
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
