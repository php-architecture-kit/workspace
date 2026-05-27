<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Func\Grammar\Env;

use PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Env\EnvDotenv;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\TokenRegion;
use PhpArchitecture\Parser\Tests\Func\Grammar\GrammarTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('func')]
final class EnvDotenvTest extends GrammarTestCase
{
    private static function dataFile(string $variant, string $name): string
    {
        return file_get_contents(__DIR__ . '/../../../Data/Env/' . $variant . '/' . $name);
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
    public function testSingleQuoted(): void
    {
        $this->assertGrammarParsing(
            string: self::dataFile('dotenv', 'single_quoted.env'),
            grammar: (new EnvDotenv())->grammar(),
            assertTokenizationResultValid: function (TokenRegion $tokenRegion, self $test): void {
                $regions = self::collectRegionsByName($tokenRegion, 'singleQuotedValue');
                $test->assertCount(3, $regions, 'Expected 3 singleQuotedValue regions');
            },
        );
    }

    #[Test]
    public function testDoubleQuotedEscapes(): void
    {
        $this->assertGrammarParsing(
            string: self::dataFile('dotenv', 'double_quoted_escapes.env'),
            grammar: (new EnvDotenv())->grammar(),
            assertTokenizationResultValid: function (TokenRegion $tokenRegion, self $test): void {
                $test->assertTrue(
                    self::containsTokenNamed($tokenRegion, 'escapeChar'),
                    'Expected escapeChar tokens in double-quoted strings',
                );
                $regions = self::collectRegionsByName($tokenRegion, 'doubleQuotedValue');
                $test->assertCount(5, $regions, 'Expected 5 doubleQuotedValue regions');
            },
        );
    }

    #[Test]
    public function testDoubleQuotedExpansion(): void
    {
        $this->assertGrammarParsing(
            string: self::dataFile('dotenv', 'double_quoted_expansion.env'),
            grammar: (new EnvDotenv())->grammar(),
            assertTokenizationResultValid: function (TokenRegion $tokenRegion, self $test): void {
                $doubleQuoted = self::collectRegionsByName($tokenRegion, 'doubleQuotedValue');
                $test->assertCount(3, $doubleQuoted, 'Expected 3 doubleQuotedValue regions');

                $test->assertTrue(
                    self::containsTokenNamed($tokenRegion, 'simpleExpansion'),
                    'Expected simpleExpansion tokens inside double-quoted strings',
                );
                $test->assertTrue(
                    self::containsTokenNamed($tokenRegion, 'bracedExpansion'),
                    'Expected bracedExpansion tokens inside double-quoted strings',
                );
            },
        );
    }

    #[Test]
    public function testLineContinuation(): void
    {
        $this->assertGrammarParsing(
            string: self::dataFile('dotenv', 'line_continuation.env'),
            grammar: (new EnvDotenv())->grammar(),
            assertTokenizationResultValid: function (TokenRegion $tokenRegion, self $test): void {
                $test->assertTrue(
                    self::containsTokenNamed($tokenRegion, 'lineContinuation'),
                    'Expected lineContinuation tokens inside double-quoted value',
                );
            },
        );
    }

    #[Test]
    public function testAdvancedExpansions(): void
    {
        $this->assertGrammarParsing(
            string: self::dataFile('dotenv', 'advanced_expansions.env'),
            grammar: (new EnvDotenv())->grammar(),
            assertTokenizationResultValid: function (TokenRegion $tokenRegion, self $test): void {
                $test->assertTrue(
                    self::containsTokenNamed($tokenRegion, 'bracedExpansion'),
                    'Expected bracedExpansion tokens with operators',
                );
                $assignments = self::collectRegionsByName($tokenRegion, 'assignment');
                $test->assertCount(6, $assignments, 'Expected 6 assignment regions');
            },
        );
    }

    #[Test]
    public function testBlockCommentDotenv(): void
    {
        $this->assertGrammarParsing(
            string: self::dataFile('dotenv', 'block_comment_dotenv.env'),
            grammar: (new EnvDotenv())->grammar(),
            assertTokenizationResultValid: function (TokenRegion $tokenRegion, self $test): void {
                $blockComments = self::collectRegionsByName($tokenRegion, 'blockComment');
                $test->assertCount(2, $blockComments, 'Expected 2 consecutive comment lines as blockComment');

                $lineComments = self::collectRegionsByName($tokenRegion, 'lineComment');
                $test->assertCount(0, $lineComments, 'Expected no isolated lineComment regions');
            },
        );
    }

    #[Test]
    public function testMixedDotenv(): void
    {
        $this->assertGrammarParsing(
            string: self::dataFile('dotenv', 'mixed_dotenv.env'),
            grammar: (new EnvDotenv())->grammar(),
        );
    }

    #[Test]
    public function testInheritsEnvironmentRules(): void
    {
        $this->assertGrammarParsing(
            string: self::dataFile('environment', 'braced_expansion.env'),
            grammar: (new EnvDotenv())->grammar(),
            assertTokenizationResultValid: function (TokenRegion $tokenRegion, self $test): void {
                $test->assertTrue(
                    self::containsTokenNamed($tokenRegion, 'bracedExpansion'),
                    'Dotenv grammar must correctly parse environment-variant braced expansions',
                );
            },
        );
    }
}
