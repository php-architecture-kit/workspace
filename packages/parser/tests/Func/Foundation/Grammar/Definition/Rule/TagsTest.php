<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Func\Foundation\Grammar\Definition\Rule;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
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
}
