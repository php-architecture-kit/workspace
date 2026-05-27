<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Func\Grammar\Env;

use PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Env\EnvEnvironment;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\Token;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\TokenRegion;
use PhpArchitecture\Parser\Tests\Func\Grammar\GrammarTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('func')]
final class EnvEnvironmentTest extends GrammarTestCase
{
    private static function dataFile(string $name): string
    {
        return file_get_contents(__DIR__ . '/../../../Data/Env/environment/' . $name);
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

    private static function collectAllRegionNames(TokenRegion $region): array
    {
        $names = [];
        foreach ($region->stream->tokens as $token) {
            if (!$token instanceof TokenRegion) {
                continue;
            }
            $names[] = $token->name;
            foreach (self::collectAllRegionNames($token) as $nested) {
                $names[] = $nested;
            }
        }
        return $names;
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
    public function testBasicAssignment(): void
    {
        $this->assertGrammarParsing(
            string: self::dataFile('basic_assignment.env'),
            grammar: (new EnvEnvironment())->grammar(),
            assertTokenizationResultValid: function (TokenRegion $tokenRegion, self $test): void {
                $assignments = self::collectRegionsByName($tokenRegion, 'assignment');
                $test->assertCount(4, $assignments, 'Expected 4 assignment regions');
            },
        );
    }

    #[Test]
    public function testSimpleExpansion(): void
    {
        $this->assertGrammarParsing(
            string: self::dataFile('simple_expansion.env'),
            grammar: (new EnvEnvironment())->grammar(),
            assertTokenizationResultValid: function (TokenRegion $tokenRegion, self $test): void {
                $test->assertTrue(
                    self::containsTokenNamed($tokenRegion, 'simpleExpansion'),
                    'Expected simpleExpansion tokens in tokenization result',
                );
            },
        );
    }

    #[Test]
    public function testBracedExpansion(): void
    {
        $this->assertGrammarParsing(
            string: self::dataFile('braced_expansion.env'),
            grammar: (new EnvEnvironment())->grammar(),
            assertTokenizationResultValid: function (TokenRegion $tokenRegion, self $test): void {
                $test->assertTrue(
                    self::containsTokenNamed($tokenRegion, 'bracedExpansion'),
                    'Expected bracedExpansion tokens in tokenization result',
                );
            },
        );
    }

    #[Test]
    public function testLineComment(): void
    {
        $this->assertGrammarParsing(
            string: self::dataFile('line_comment.env'),
            grammar: (new EnvEnvironment())->grammar(),
            assertTokenizationResultValid: function (TokenRegion $tokenRegion, self $test): void {
                $lineComments = self::collectRegionsByName($tokenRegion, 'lineComment');
                $test->assertCount(1, $lineComments, 'Expected 1 lineComment region');

                $blockComments = self::collectRegionsByName($tokenRegion, 'blockComment');
                $test->assertCount(0, $blockComments, 'Expected no blockComment regions');
            },
        );
    }

    #[Test]
    public function testBlockComment(): void
    {
        $this->assertGrammarParsing(
            string: self::dataFile('block_comment.env'),
            grammar: (new EnvEnvironment())->grammar(),
            assertTokenizationResultValid: function (TokenRegion $tokenRegion, self $test): void {
                $blockComments = self::collectRegionsByName($tokenRegion, 'blockComment');
                $test->assertCount(3, $blockComments, 'Expected all 3 consecutive comment lines to be blockComment');

                $lineComments = self::collectRegionsByName($tokenRegion, 'lineComment');
                $test->assertCount(0, $lineComments, 'Expected no isolated lineComment regions');
            },
        );
    }

    #[Test]
    public function testMixedComments(): void
    {
        $this->assertGrammarParsing(
            string: self::dataFile('mixed_comments.env'),
            grammar: (new EnvEnvironment())->grammar(),
            assertTokenizationResultValid: function (TokenRegion $tokenRegion, self $test): void {
                $lineComments = self::collectRegionsByName($tokenRegion, 'lineComment');
                $test->assertCount(2, $lineComments, 'Expected 2 isolated lineComment regions');

                $blockComments = self::collectRegionsByName($tokenRegion, 'blockComment');
                $test->assertCount(2, $blockComments, 'Expected 2 consecutive blockComment regions');
            },
        );
    }

    #[Test]
    public function testEmptyLines(): void
    {
        $this->assertGrammarParsing(
            string: self::dataFile('empty_lines.env'),
            grammar: (new EnvEnvironment())->grammar(),
        );
    }

    #[Test]
    public function testWhitespaceAroundEquals(): void
    {
        $this->assertGrammarParsing(
            string: self::dataFile('whitespace_around_equals.env'),
            grammar: (new EnvEnvironment())->grammar(),
            assertTokenizationResultValid: function (TokenRegion $tokenRegion, self $test): void {
                $assignments = self::collectRegionsByName($tokenRegion, 'assignment');
                $test->assertCount(3, $assignments, 'Expected 3 assignment regions');
            },
        );
    }

    #[Test]
    public function testEmptyFile(): void
    {
        $this->assertGrammarParsing(
            string: self::dataFile('empty_file.env'),
            grammar: (new EnvEnvironment())->grammar(),
        );
    }

    #[Test]
    public function testCommentOnly(): void
    {
        $this->assertGrammarParsing(
            string: self::dataFile('comment_only.env'),
            grammar: (new EnvEnvironment())->grammar(),
            assertTokenizationResultValid: function (TokenRegion $tokenRegion, self $test): void {
                $comments = array_merge(
                    self::collectRegionsByName($tokenRegion, 'lineComment'),
                    self::collectRegionsByName($tokenRegion, 'blockComment'),
                );
                $test->assertNotEmpty($comments, 'Expected at least one comment region');
            },
        );
    }

    #[Test]
    public function testLowerCaseKeys(): void
    {
        $this->assertGrammarParsing(
            string: "my_var=hello\n",
            grammar: (new EnvEnvironment())->grammar(),
            assertTokenizationResultValid: function (TokenRegion $tokenRegion, self $test): void {
                $assignments = self::collectRegionsByName($tokenRegion, 'assignment');
                $test->assertCount(1, $assignments, 'Expected 1 assignment region for lowercase key');
            },
        );
    }
}
