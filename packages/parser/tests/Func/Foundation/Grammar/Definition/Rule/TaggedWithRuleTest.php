<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Func\Foundation\Grammar\Definition\Rule;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\NodeAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawRegionAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeType;
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
    public function shouldProduceNodeAttributeNamedAfterTagWithAlternativesInMeta(): void
    {
        // TagToChoiceCompiler replaces Rule::taggedWith('keyword') with Rule::choice('keyword', [...], NodeType::Tag).
        // SequenceNodeEnricher then spreads the single Tag-typed alternative inline as
        // NodeType::Node — ChoiceAttribute was removed, so the tag's alternatives now
        // live in meta['alternatives'] on the resulting NodeAttribute.
        $grammar = $this->buildGrammar();

        $this->assertGrammarParsing(
            string: 'null',
            grammar: $grammar,
            requireBofEof: false,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $attributes = $node->getAttributes();

                $test->assertCount(1, $attributes);
                $test->assertInstanceOf(NodeAttribute::class, $attributes[0]);
                $test->assertSame('keyword', $attributes[0]->getName());
                $test->assertSame(['null', 'true'], $attributes[0]->meta['alternatives']);
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

    // --- Tag covering exactly one rule (no ambiguity to disambiguate) ---

    #[Test]
    public function shouldResolveBareTagCoveringExactlyOneRawRegionToThatRegionsOwnType(): void
    {
        // A bare reference to a tag is only wrapped as NodeType::Node to make a choice
        // between several covered alternatives addressable (see
        // shouldProduceNodeAttributeNamedAfterTagWithAlternativesInMeta, where the tag
        // covers two rules). When the tag covers exactly one rule, there is no choice to
        // disambiguate — the slot must resolve to that single rule's own NodeType
        // (Raw here), not be forced into a spurious Node wrapper around it.
        $grammar = $this->buildSingleRuleTagGrammar();

        $this->assertGrammarParsing(
            string: '"abc"',
            grammar: $grammar,
            requireBofEof: false,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $attributes = $node->getAttributes();

                $test->assertCount(1, $attributes);
                $test->assertInstanceOf(
                    RawRegionAttribute::class,
                    $attributes[0],
                    'a tag covering exactly one Raw region must resolve to that region\'s own type, not be wrapped as Node',
                );
                $test->assertSame('quoted', $attributes[0]->name);
                $test->assertSame('abc', $attributes[0]->content);
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

    private function buildSingleRuleTagGrammar(): Grammar
    {
        $grammar = new Grammar('tagged-test-single');
        $grammar->global->add(
            Rule::token('quote', '"', type: NodeType::Structure)
                ->startRegion('quoted', true)
                ->add(
                    Rule::expr('chars', '[^"]+'),
                )
                ->setNodeType(NodeType::Raw)
                ->closeWith(Rule::token('quote', '"', type: NodeType::Structure))
                ->addTag('value'),
        );
        $grammar->global->withRootSequence('value[content]');

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
