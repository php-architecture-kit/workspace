<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Func\Foundation\Grammar\Definition\Rule;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\ChoiceAttribute;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\Token;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\TokenRegion;
use PhpArchitecture\Parser\Tests\Func\Grammar\GrammarTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('func')]
final class TaggedWithRuleTest extends GrammarTestCase
{
    // --- Tokenization ---

    #[Test]
    public function shouldMatchFirstRuleWithGivenTag(): void
    {
        $grammar = $this->buildGrammar();

        $this->assertGrammarParsing(
            string: 'null',
            grammar: $grammar,
            requireBofEof: false,
            assertTokenizationResultValid: function (TokenRegion $region, self $test): void {
                $tokens = $test->getContentTokens($region);

                $test->assertCount(1, $tokens);
                $test->assertSame('null', $tokens[0]->name);
            },
        );
    }

    #[Test]
    public function shouldMatchOtherRuleWithSameTag(): void
    {
        $grammar = $this->buildGrammar();

        $this->assertGrammarParsing(
            string: 'true',
            grammar: $grammar,
            requireBofEof: false,
            assertTokenizationResultValid: function (TokenRegion $region, self $test): void {
                $tokens = $test->getContentTokens($region);

                $test->assertCount(1, $tokens);
                $test->assertSame('true', $tokens[0]->name);
            },
        );
    }

    // --- Parsing result (NodeInterface) ---

    #[Test]
    public function shouldProduceChoiceAttributeNamedAfterTag(): void
    {
        // TagToChoiceCompiler replaces Rule::taggedWith('keyword') with Rule::choice('keyword', [...], NodeType::Node).
        // Rule::choice always produces a ChoiceAttribute.
        $grammar = $this->buildGrammar();

        $this->assertGrammarParsing(
            string: 'null',
            grammar: $grammar,
            requireBofEof: false,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $attributes = $node->getAttributes();

                $test->assertCount(1, $attributes);
                $test->assertInstanceOf(ChoiceAttribute::class, $attributes[0]);
                $test->assertSame('keyword', $attributes[0]->name);
            },
        );
    }

    #[Test]
    public function shouldNodeAttributeCastToMatchedKeyword(): void
    {
        $grammar = $this->buildGrammar();

        $this->assertGrammarParsing(
            string: 'true',
            grammar: $grammar,
            requireBofEof: false,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $test->assertSame('true', (string) $node->getAttributes()[0]);
            },
        );
    }

    #[Test]
    public function shouldMatchWithoutExplicitTaggedWithRule(): void
    {
        // TagToChoiceCompiler auto-creates a choice rule for any tag present in the region.
        // Rule::taggedWith() is not required; the tag itself is sufficient.
        $grammar = new Grammar('tagged-test');
        $grammar->global->add(Rule::keyword('null', caseSensitive: true, tags: ['keyword']));
        $grammar->global->add(Rule::keyword('true', caseSensitive: true, tags: ['keyword']));
        $grammar->global->withRootSequence('keyword');

        $this->assertGrammarParsing(
            string: 'null',
            grammar: $grammar,
            requireBofEof: false,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $test->assertSame('null', (string) $node);
            },
        );
    }

    // --- Helpers ---

    private function buildGrammar(): Grammar
    {
        $grammar = new Grammar('tagged-test');
        $grammar->global->add(Rule::keyword('null', caseSensitive: true, tags: ['keyword']));
        $grammar->global->add(Rule::keyword('true', caseSensitive: true, tags: ['keyword']));
        $grammar->global->add(Rule::taggedWith('keyword'));
        $grammar->global->withRootSequence('keyword');

        return $grammar;
    }

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
