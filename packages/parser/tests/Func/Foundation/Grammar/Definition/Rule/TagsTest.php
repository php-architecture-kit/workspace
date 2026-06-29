<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Func\Foundation\Grammar\Definition\Rule;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\TokenRegion;
use PhpArchitecture\Parser\Tests\Func\Grammar\GrammarTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('func')]
final class TagsTest extends GrammarTestCase
{
    #[Test]
    public function shouldAddTagMakesTokenCarryTag(): void
    {
        $grammar = new Grammar('tags-test');
        $grammar->global->add(Rule::token('x', 'x')->addTag('my-tag'));

        $this->assertGrammarParsing(
            string: 'x',
            grammar: $grammar,
            assertTokenizationResultValid: function (TokenRegion $tokenRegion, self $test): void {
                $token = $tokenRegion->stream->tokens[0];

                $test->assertContains('my-tag', $token->tags);
            },
            requireBofEof: false,
        );
    }

    #[Test]
    public function shouldRemoveTagDoesNotCarryRemovedTag(): void
    {
        $grammar = new Grammar('tags-test');
        $grammar->global->add(Rule::token('x', 'x')->addTag('my-tag')->removeTag('my-tag'));

        $this->assertGrammarParsing(
            string: 'x',
            grammar: $grammar,
            assertTokenizationResultValid: function (TokenRegion $tokenRegion, self $test): void {
                $token = $tokenRegion->stream->tokens[0];

                $test->assertNotContains('my-tag', $token->tags);
            },
            requireBofEof: false,
        );
    }

    #[Test]
    public function shouldReplaceTagsMakesOnlyNewTagPresent(): void
    {
        $grammar = new Grammar('tags-test');
        $grammar->global->add(
            Rule::token('x', 'x')->addTag('old-tag')->replaceTags(['new-tag']),
        );

        $this->assertGrammarParsing(
            string: 'x',
            grammar: $grammar,
            assertTokenizationResultValid: function (TokenRegion $tokenRegion, self $test): void {
                $token = $tokenRegion->stream->tokens[0];

                $test->assertContains('new-tag', $token->tags);
                $test->assertNotContains('old-tag', $token->tags);
            },
            requireBofEof: false,
        );
    }

    #[Test]
    public function shouldSpreadTagAlternativesWhenTagIsOneOfManyChoiceAlternatives(): void
    {
        // `string` is a tag carried by the `quoted` rule. The `primitive` choice lists
        // `string` alongside literal keywords. The compiler must spread the tag into its
        // covered alternative, so primitive = false|null|true|quoted.
        $grammar = new Grammar('tag-spread-test');
        $grammar->global->add(Rule::keyword('false', caseSensitive: true));
        $grammar->global->add(Rule::keyword('null', caseSensitive: true));
        $grammar->global->add(Rule::keyword('true', caseSensitive: true));
        $grammar->global->add(Rule::expr('quoted', '"[^"]*"')->addTag('string'));
        $grammar->global->add(Rule::choice('primitive', ['false', 'null', 'true', 'string']));
        $grammar->global->withRootSequence('primitive');

        // alternative coming from the tag (`string` -> `quoted`)
        $this->assertGrammarParsing(
            string: '"hello"',
            grammar: $grammar,
            requireBofEof: false,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $test->assertSame('"hello"', (string) $node);
            },
        );

        // literal alternative still parses after the spread (regression guard)
        $this->assertGrammarParsing(
            string: 'true',
            grammar: $grammar,
            requireBofEof: false,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $test->assertSame('true', (string) $node);
            },
        );
    }
}
