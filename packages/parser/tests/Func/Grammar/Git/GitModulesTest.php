<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Func\Grammar\Git;

use PhpArchitecture\Parser\Foundation\Tokenization\Model\TokenRegion;
use PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Git\GitModules;
use PhpArchitecture\Parser\Tests\Func\Grammar\GrammarTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('func')]
final class GitModulesTest extends GrammarTestCase
{
    private static function dataFile(string $name): string
    {
        return file_get_contents(__DIR__ . '/../../../Data/Git/gitmodules/' . $name);
    }

    /** @return TokenRegion[] */
    private static function collectRegionsByName(TokenRegion $region, string $name): array
    {
        $result = [];
        foreach ($region->stream->tokens as $token) {
            if (!$token instanceof TokenRegion) {
                continue;
            }
            if ($token->name === $name) {
                $result[] = $token;
            }
            foreach (self::collectRegionsByName($token, $name) as $nested) {
                $result[] = $nested;
            }
        }
        return $result;
    }

    private static function containsTokenNamed(TokenRegion $region, string $name): bool
    {
        foreach ($region->stream->tokens as $token) {
            if ($token->name === $name) {
                return true;
            }
            if ($token instanceof TokenRegion && self::containsTokenNamed($token, $name)) {
                return true;
            }
        }
        return false;
    }

    #[Test]
    public function testQuotedSubsection(): void
    {
        $this->assertGrammarParsing(
            string: self::dataFile('quoted_subsection.gitmodules'),
            grammar: (new GitModules())->grammar(),
            assertTokenizationResultValid: function (TokenRegion $tokenRegion, self $test): void {
                $test->assertCount(1, self::collectRegionsByName($tokenRegion, 'quotedSectionHeader'));
                $test->assertCount(2, self::collectRegionsByName($tokenRegion, 'keyValueAssignment'));
                $test->assertCount(0, self::collectRegionsByName($tokenRegion, 'dottedSectionHeader'));
                $test->assertCount(0, self::collectRegionsByName($tokenRegion, 'bareSectionHeader'));
            },
        );
    }

    #[Test]
    public function testQuotedSubsectionEscapedQuote(): void
    {
        $this->assertGrammarParsing(
            string: self::dataFile('quoted_subsection_escaped_quote.gitmodules'),
            grammar: (new GitModules())->grammar(),
            assertTokenizationResultValid: function (TokenRegion $tokenRegion, self $test): void {
                $test->assertCount(1, self::collectRegionsByName($tokenRegion, 'quotedSectionHeader'));
                $test->assertTrue(
                    self::containsTokenNamed($tokenRegion, 'subsectionEscape'),
                    'Expected subsectionEscape tokens for escaped quotes inside subsection name',
                );
            },
        );
    }

    #[Test]
    public function testQuotedSubsectionWithSpace(): void
    {
        $this->assertGrammarParsing(
            string: self::dataFile('quoted_subsection_with_space.gitmodules'),
            grammar: (new GitModules())->grammar(),
            assertTokenizationResultValid: function (TokenRegion $tokenRegion, self $test): void {
                $test->assertCount(1, self::collectRegionsByName($tokenRegion, 'quotedSectionHeader'));
            },
        );
    }

    #[Test]
    public function testQuotedSubsectionEscapeDropRule(): void
    {
        $this->assertGrammarParsing(
            string: self::dataFile('quoted_subsection_escape_drop_rule.gitmodules'),
            grammar: (new GitModules())->grammar(),
            assertTokenizationResultValid: function (TokenRegion $tokenRegion, self $test): void {
                $test->assertCount(1, self::collectRegionsByName($tokenRegion, 'quotedSectionHeader'));
                // `\n` inside a subsection name uses the drop-rule (backslash dropped, 'n' kept
                // as a literal letter) — confirmed by Step 24 validation against config.c
                $test->assertTrue(
                    self::containsTokenNamed($tokenRegion, 'subsectionEscape'),
                    'Expected subsectionEscape token for \\n inside subsection name (drop-rule: becomes literal n)',
                );
            },
        );
    }

    #[Test]
    public function testDottedSection(): void
    {
        $this->assertGrammarParsing(
            string: self::dataFile('dotted_section.gitmodules'),
            grammar: (new GitModules())->grammar(),
            assertTokenizationResultValid: function (TokenRegion $tokenRegion, self $test): void {
                $test->assertCount(1, self::collectRegionsByName($tokenRegion, 'dottedSectionHeader'));
                $test->assertCount(0, self::collectRegionsByName($tokenRegion, 'quotedSectionHeader'));
                $test->assertCount(0, self::collectRegionsByName($tokenRegion, 'bareSectionHeader'));
                $test->assertCount(2, self::collectRegionsByName($tokenRegion, 'keyValueAssignment'));
            },
        );
    }

    #[Test]
    public function testBareSection(): void
    {
        $this->assertGrammarParsing(
            string: self::dataFile('bare_section.gitmodules'),
            grammar: (new GitModules())->grammar(),
            assertTokenizationResultValid: function (TokenRegion $tokenRegion, self $test): void {
                $test->assertCount(1, self::collectRegionsByName($tokenRegion, 'bareSectionHeader'));
                $test->assertCount(0, self::collectRegionsByName($tokenRegion, 'quotedSectionHeader'));
                $test->assertCount(0, self::collectRegionsByName($tokenRegion, 'dottedSectionHeader'));
            },
        );
    }

    #[Test]
    public function testEmptySection(): void
    {
        $this->assertGrammarParsing(
            string: self::dataFile('empty_section.gitmodules'),
            grammar: (new GitModules())->grammar(),
            assertTokenizationResultValid: function (TokenRegion $tokenRegion, self $test): void {
                $test->assertCount(1, self::collectRegionsByName($tokenRegion, 'quotedSectionHeader'));
                $test->assertCount(0, self::collectRegionsByName($tokenRegion, 'keyValueAssignment'));
                $test->assertCount(0, self::collectRegionsByName($tokenRegion, 'booleanShorthandAssignment'));
            },
        );
    }

    #[Test]
    public function testHeaderTrailingVariable(): void
    {
        // `[submodule "name"] key = value` — the remainder of a header line is a variable assignment
        $this->assertGrammarParsing(
            string: self::dataFile('header_trailing_variable.gitmodules'),
            grammar: (new GitModules())->grammar(),
            assertTokenizationResultValid: function (TokenRegion $tokenRegion, self $test): void {
                $test->assertCount(1, self::collectRegionsByName($tokenRegion, 'quotedSectionHeader'));
                $test->assertCount(2, self::collectRegionsByName($tokenRegion, 'keyValueAssignment'));
            },
        );
    }

    #[Test]
    public function testBooleanShorthand(): void
    {
        $this->assertGrammarParsing(
            string: self::dataFile('boolean_shorthand.gitmodules'),
            grammar: (new GitModules())->grammar(),
            assertTokenizationResultValid: function (TokenRegion $tokenRegion, self $test): void {
                $test->assertCount(1, self::collectRegionsByName($tokenRegion, 'booleanShorthandAssignment'));
                $test->assertCount(0, self::collectRegionsByName($tokenRegion, 'keyValueAssignment'));
            },
        );
    }

    #[Test]
    public function testMultiValuedKey(): void
    {
        $this->assertGrammarParsing(
            string: self::dataFile('multi_valued_key.gitmodules'),
            grammar: (new GitModules())->grammar(),
            assertTokenizationResultValid: function (TokenRegion $tokenRegion, self $test): void {
                // Both occurrences of fetchRecurseSubmodules are independent sibling assignments
                $test->assertCount(2, self::collectRegionsByName($tokenRegion, 'keyValueAssignment'));
            },
        );
    }

    #[Test]
    public function testCaseInsensitiveKeys(): void
    {
        $this->assertGrammarParsing(
            string: self::dataFile('case_insensitive_keys.gitmodules'),
            grammar: (new GitModules())->grammar(),
            assertTokenizationResultValid: function (TokenRegion $tokenRegion, self $test): void {
                $test->assertCount(2, self::collectRegionsByName($tokenRegion, 'keyValueAssignment'));
            },
        );
    }

    #[Test]
    public function testValueQuoted(): void
    {
        $this->assertGrammarParsing(
            string: self::dataFile('value_quoted.gitmodules'),
            grammar: (new GitModules())->grammar(),
            assertTokenizationResultValid: function (TokenRegion $tokenRegion, self $test): void {
                $test->assertCount(1, self::collectRegionsByName($tokenRegion, 'quotedSegment'));
            },
        );
    }

    #[Test]
    public function testValueEscapes(): void
    {
        $this->assertGrammarParsing(
            string: self::dataFile('value_escapes.gitmodules'),
            grammar: (new GitModules())->grammar(),
            assertTokenizationResultValid: function (TokenRegion $tokenRegion, self $test): void {
                $test->assertTrue(
                    self::containsTokenNamed($tokenRegion, 'valueEscape'),
                    'Expected valueEscape tokens for escape sequences inside quoted value',
                );
                $test->assertCount(1, self::collectRegionsByName($tokenRegion, 'quotedSegment'));
            },
        );
    }

    #[Test]
    public function testMixedQuoteConcatenation(): void
    {
        // `prefix"-mid-"suffix\tend` — unquoted+quoted+unquoted segments in one value,
        // plus a value escape (\t) outside a quoted segment
        $this->assertGrammarParsing(
            string: self::dataFile('mixed_quote_concatenation.gitmodules'),
            grammar: (new GitModules())->grammar(),
            assertTokenizationResultValid: function (TokenRegion $tokenRegion, self $test): void {
                $test->assertCount(1, self::collectRegionsByName($tokenRegion, 'quotedSegment'));
                $test->assertTrue(
                    self::containsTokenNamed($tokenRegion, 'valueEscape'),
                    'Expected valueEscape token for \\t outside a quoted segment',
                );
            },
        );
    }

    #[Test]
    public function testLineContinuation(): void
    {
        $this->assertGrammarParsing(
            string: self::dataFile('line_continuation.gitmodules'),
            grammar: (new GitModules())->grammar(),
            assertTokenizationResultValid: function (TokenRegion $tokenRegion, self $test): void {
                $test->assertTrue(
                    self::containsTokenNamed($tokenRegion, 'lineContinuation'),
                    'Expected lineContinuation token for backslash-newline in value',
                );
            },
        );
    }

    #[Test]
    public function testInlineCommentHash(): void
    {
        $this->assertGrammarParsing(
            string: self::dataFile('inline_comment_hash.gitmodules'),
            grammar: (new GitModules())->grammar(),
            assertTokenizationResultValid: function (TokenRegion $tokenRegion, self $test): void {
                $test->assertCount(1, self::collectRegionsByName($tokenRegion, 'inlineComment'));
            },
        );
    }

    #[Test]
    public function testInlineCommentSemicolon(): void
    {
        $this->assertGrammarParsing(
            string: self::dataFile('inline_comment_semicolon.gitmodules'),
            grammar: (new GitModules())->grammar(),
            assertTokenizationResultValid: function (TokenRegion $tokenRegion, self $test): void {
                $test->assertCount(1, self::collectRegionsByName($tokenRegion, 'inlineComment'));
            },
        );
    }

    #[Test]
    public function testFullLineComments(): void
    {
        $this->assertGrammarParsing(
            string: self::dataFile('full_line_comments.gitmodules'),
            grammar: (new GitModules())->grammar(),
            assertTokenizationResultValid: function (TokenRegion $tokenRegion, self $test): void {
                // One `#` comment and one `;` comment at the top level
                $test->assertCount(2, self::collectRegionsByName($tokenRegion, 'lineComment'));
            },
        );
    }

    #[Test]
    public function testBlankLines(): void
    {
        $this->assertGrammarParsing(
            string: self::dataFile('blank_lines.gitmodules'),
            grammar: (new GitModules())->grammar(),
            assertTokenizationResultValid: function (TokenRegion $tokenRegion, self $test): void {
                $test->assertCount(2, self::collectRegionsByName($tokenRegion, 'quotedSectionHeader'));
                $test->assertCount(2, self::collectRegionsByName($tokenRegion, 'keyValueAssignment'));
                $test->assertTrue(
                    self::containsTokenNamed($tokenRegion, 'emptyLine'),
                    'Expected emptyLine whitespace region for blank line between sections',
                );
            },
        );
    }

    #[Test]
    public function testCrlfLineEndings(): void
    {
        $this->assertGrammarParsing(
            string: self::dataFile('crlf_line_endings.gitmodules'),
            grammar: (new GitModules())->grammar(),
        );
    }

    #[Test]
    public function testMixed(): void
    {
        // Composite file — no extra assertions, roundtrip correctness only
        $this->assertGrammarParsing(
            string: self::dataFile('mixed.gitmodules'),
            grammar: (new GitModules())->grammar(),
        );
    }
}
