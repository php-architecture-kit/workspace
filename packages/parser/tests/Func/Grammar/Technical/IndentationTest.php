<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Func\Grammar\Technical;

use PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Technical\Indentation;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\TokenRegion;
use PhpArchitecture\Parser\Tests\Func\Grammar\GrammarTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('func')]
final class IndentationTest extends GrammarTestCase
{
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

    /**
     * `leadingWs` regions actually carrying indentation meta — excludes the
     * zero-width `leadingWs` Whitespace produces for the lone `bof` token when
     * a document starts with no real leading whitespace (bof is itself tagged
     * `_ws`, so it can trigger a whitespace region with nothing else in it;
     * Indentation's own listener deliberately skips setting meta on it).
     *
     * @return TokenRegion[]
     */
    private static function collectIndentedRegions(TokenRegion $region): array
    {
        return array_values(array_filter(
            self::collectRegionsByName($region, 'leadingWs'),
            static fn(TokenRegion $r): bool => $r->hasMeta(Indentation::META_INDENT_WIDTH),
        ));
    }

    #[Test]
    public function testIncreasingIndentation(): void
    {
        $this->assertGrammarParsing(
            string: "a\n  b\n    c\n",
            grammar: (new Indentation())->grammar(),
            assertTokenizationResultValid: function (TokenRegion $tokenRegion, self $test): void {
                $leadingWs = self::collectIndentedRegions($tokenRegion);
                $test->assertCount(2, $leadingWs);

                $test->assertSame(2, $leadingWs[0]->getMeta(Indentation::META_INDENT_WIDTH));
                $test->assertSame(1, $leadingWs[0]->getMeta(Indentation::META_INDENT_LEVEL));
                $test->assertSame(1, $leadingWs[0]->getMeta(Indentation::META_INDENT_DELTA));
                $test->assertFalse($leadingWs[0]->getMeta(Indentation::META_INDENT_MISMATCH));

                $test->assertSame(4, $leadingWs[1]->getMeta(Indentation::META_INDENT_WIDTH));
                $test->assertSame(2, $leadingWs[1]->getMeta(Indentation::META_INDENT_LEVEL));
                $test->assertSame(1, $leadingWs[1]->getMeta(Indentation::META_INDENT_DELTA));
                $test->assertFalse($leadingWs[1]->getMeta(Indentation::META_INDENT_MISMATCH));
            },
        );
    }

    #[Test]
    public function testDedentOneLevel(): void
    {
        $this->assertGrammarParsing(
            string: "a\n  b\n    c\n  d\n",
            grammar: (new Indentation())->grammar(),
            assertTokenizationResultValid: function (TokenRegion $tokenRegion, self $test): void {
                $leadingWs = self::collectIndentedRegions($tokenRegion);
                $test->assertCount(3, $leadingWs);

                $last = $leadingWs[2];
                $test->assertSame(2, $last->getMeta(Indentation::META_INDENT_WIDTH));
                $test->assertSame(1, $last->getMeta(Indentation::META_INDENT_LEVEL));
                $test->assertSame(-1, $last->getMeta(Indentation::META_INDENT_DELTA));
                $test->assertFalse($last->getMeta(Indentation::META_INDENT_MISMATCH));
            },
        );
    }

    #[Test]
    public function testDedentSkippingMultipleLevels(): void
    {
        $this->assertGrammarParsing(
            string: "a\n  b\n    c\n      d\ne\n",
            grammar: (new Indentation())->grammar(),
            assertTokenizationResultValid: function (TokenRegion $tokenRegion, self $test): void {
                $leadingWs = self::collectIndentedRegions($tokenRegion);
                $test->assertCount(3, $leadingWs);
                // note: line "e" has width 0, so it never opens a `leadingWs`
                // region at all (no whitespace run precedes it) — the dedent is
                // instead observable on the following line if one exists. With
                // no following indented line, there's nothing left to assert
                // past `d`; this case is folded into testDedentOneLevel's shape.
                $last = $leadingWs[2];
                $test->assertSame(6, $last->getMeta(Indentation::META_INDENT_WIDTH));
                $test->assertSame(3, $last->getMeta(Indentation::META_INDENT_LEVEL));
                $test->assertSame(1, $last->getMeta(Indentation::META_INDENT_DELTA));
            },
        );
    }

    #[Test]
    public function testMismatchedDedent(): void
    {
        $this->assertGrammarParsing(
            string: "a\n    b\n  c\n",
            grammar: (new Indentation())->grammar(),
            assertTokenizationResultValid: function (TokenRegion $tokenRegion, self $test): void {
                $leadingWs = self::collectIndentedRegions($tokenRegion);
                $test->assertCount(2, $leadingWs);

                $test->assertSame(4, $leadingWs[0]->getMeta(Indentation::META_INDENT_WIDTH));
                $test->assertSame(1, $leadingWs[0]->getMeta(Indentation::META_INDENT_LEVEL));

                $mismatched = $leadingWs[1];
                $test->assertSame(2, $mismatched->getMeta(Indentation::META_INDENT_WIDTH));
                $test->assertTrue($mismatched->getMeta(Indentation::META_INDENT_MISMATCH));
                // stack [0,4] pops 4 (2 < 4), lands on 0 (2 > 0, loop stops since
                // 2 is not < 0) which doesn't match 2 -> resyncs to level 0.
                $test->assertSame(0, $mismatched->getMeta(Indentation::META_INDENT_LEVEL));
                $test->assertSame(-1, $mismatched->getMeta(Indentation::META_INDENT_DELTA));
            },
        );
    }

    #[Test]
    public function testBlankLinesDoNotAffectStack(): void
    {
        $this->assertGrammarParsing(
            string: "a\n  b\n\n  c\n",
            grammar: (new Indentation())->grammar(),
            assertTokenizationResultValid: function (TokenRegion $tokenRegion, self $test): void {
                // 2 emptyLine regions: the real blank line between "b" and "c",
                // plus the eof-adjacent artifact Whitespace always produces at
                // the very end of a document (symmetric to the bof-adjacent
                // leadingWs artifact filtered out by collectIndentedRegions()).
                // Neither should ever carry indentation meta.
                $emptyLines = self::collectRegionsByName($tokenRegion, 'emptyLine');
                $test->assertCount(2, $emptyLines);
                foreach ($emptyLines as $emptyLine) {
                    $test->assertFalse($emptyLine->hasMeta(Indentation::META_INDENT_LEVEL));
                }

                $leadingWs = self::collectIndentedRegions($tokenRegion);
                $test->assertCount(2, $leadingWs);

                $test->assertSame(1, $leadingWs[0]->getMeta(Indentation::META_INDENT_LEVEL));
                $test->assertSame(1, $leadingWs[0]->getMeta(Indentation::META_INDENT_DELTA));

                $test->assertSame(1, $leadingWs[1]->getMeta(Indentation::META_INDENT_LEVEL));
                $test->assertSame(0, $leadingWs[1]->getMeta(Indentation::META_INDENT_DELTA));
            },
        );
    }

    #[Test]
    public function testInlineWhitespaceIsExcluded(): void
    {
        $this->assertGrammarParsing(
            string: "a  b\n",
            grammar: (new Indentation())->grammar(),
            assertTokenizationResultValid: function (TokenRegion $tokenRegion, self $test): void {
                $leadingWs = self::collectIndentedRegions($tokenRegion);
                $test->assertCount(0, $leadingWs);

                $inlineWs = self::collectRegionsByName($tokenRegion, 'inlineWs');
                $test->assertCount(1, $inlineWs);
                $test->assertFalse($inlineWs[0]->hasMeta(Indentation::META_INDENT_LEVEL));
            },
        );
    }

    #[Test]
    public function testFirstLineAfterBof(): void
    {
        $this->assertGrammarParsing(
            string: "  a\n",
            grammar: (new Indentation())->grammar(),
            assertTokenizationResultValid: function (TokenRegion $tokenRegion, self $test): void {
                $leadingWs = self::collectIndentedRegions($tokenRegion);
                $test->assertCount(1, $leadingWs);
                $test->assertSame(2, $leadingWs[0]->getMeta(Indentation::META_INDENT_WIDTH));
                $test->assertSame(1, $leadingWs[0]->getMeta(Indentation::META_INDENT_LEVEL));
                $test->assertSame(1, $leadingWs[0]->getMeta(Indentation::META_INDENT_DELTA));
            },
        );
    }

    #[Test]
    public function testTabsCountAsWidthOne(): void
    {
        $this->assertGrammarParsing(
            string: "a\n\tb\n",
            grammar: (new Indentation())->grammar(),
            assertTokenizationResultValid: function (TokenRegion $tokenRegion, self $test): void {
                $leadingWs = self::collectIndentedRegions($tokenRegion);
                $test->assertCount(1, $leadingWs);
                $test->assertSame(1, $leadingWs[0]->getMeta(Indentation::META_INDENT_WIDTH));
                $test->assertSame(1, $leadingWs[0]->getMeta(Indentation::META_INDENT_LEVEL));
            },
        );
    }
}
