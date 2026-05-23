<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Func\Foundation\Grammar\Definition\Rule;

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
final class ExprRuleTest extends GrammarTestCase
{
    // --- Tokenization ---

    #[Test]
    public function shouldMatchMultiCharacterPattern(): void
    {
        $grammar = new Grammar('expr-test');
        $grammar->global->add(Rule::expr('digits', '[0-9]+'));

        $this->assertGrammarParsing(
            string: '123',
            grammar: $grammar,
            assertTokenizationResultValid: function (TokenRegion $region, self $test): void {
                $tokens = $test->getContentTokens($region);

                $test->assertCount(1, $tokens);
                $test->assertSame('digits', $tokens[0]->name);
                $test->assertSame('123', $tokens[0]->raw);
            },
        );
    }

    #[Test]
    public function shouldMatchLongestPossibleSubstring(): void
    {
        $grammar = new Grammar('expr-test');
        $grammar->global->add(Rule::expr('digits', '[0-9]+'));

        $this->assertGrammarParsing(
            string: '123abc',
            grammar: $grammar,
            assertTokenizationResultValid: function (TokenRegion $region, self $test): void {
                $tokens = $test->getContentTokens($region);

                $test->assertSame('digits', $tokens[0]->name);
                $test->assertSame('123', $tokens[0]->raw);
            },
        );
    }

    #[Test]
    public function shouldBeCaseInsensitiveByDefault(): void
    {
        $grammar = new Grammar('expr-test');
        $grammar->global->add(Rule::expr('word', '[a-z]+'));

        $this->assertGrammarParsing(
            string: 'HELLO',
            grammar: $grammar,
            assertTokenizationResultValid: function (TokenRegion $region, self $test): void {
                $tokens = $test->getContentTokens($region);

                $test->assertCount(1, $tokens);
                $test->assertSame('word', $tokens[0]->name);
                $test->assertSame('HELLO', $tokens[0]->raw);
            },
        );
    }

    #[Test]
    public function shouldMatchCaseSensitivelyWhenEnabled(): void
    {
        $grammar = new Grammar('expr-test');
        $grammar->global->add(Rule::expr('word', '[a-z]+', caseSensitive: true));

        $this->assertGrammarParsing(
            string: 'HELLO',
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

    // --- Parsing result (NodeInterface) ---

    #[Test]
    public function shouldCastNodeToStringAsMatchedInput(): void
    {
        $grammar = new Grammar('expr-test');
        $grammar->global->add(Rule::expr('digits', '[0-9]+'));

        $this->assertGrammarParsing(
            string: '42',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $test->assertSame('42', (string) $node);
            },
        );
    }

    #[Test]
    public function shouldProduceSingleRawContentAttributeByDefault(): void
    {
        $grammar = new Grammar('expr-test');
        $grammar->global->add(Rule::expr('digits', '[0-9]+'));

        $this->assertGrammarParsing(
            string: '42',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $attributes = $node->getAttributes();

                $test->assertCount(1, $attributes);
                $test->assertInstanceOf(RawContentAttribute::class, $attributes[0]);
                $test->assertSame('42', $attributes[0]->content);
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
