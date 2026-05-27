<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Func\Foundation\Grammar\Definition\Pratt;

use PhpArchitecture\Parser\Foundation\Tokenization\Model\TokenRegion;
use PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Math\MathExpression;
use PhpArchitecture\Parser\Tests\Func\Grammar\GrammarTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('func')]
final class PrattRegionTest extends GrammarTestCase
{
    #[Test]
    public function shouldGroupHigherPrecedenceOperatorAsSubExpression(): void
    {
        // 1 + 2 * 3 → binaryExpression(1, +, binaryExpression(2, *, 3))
        $this->assertGrammarParsing(
            string: '1 + 2 * 3',
            grammar: (new MathExpression())->grammar(),
            assertTokenizationResultValid: function (TokenRegion $root, self $test): void {
                // $root IS the expression region — stream: [binaryExpression(1, +, binaryExpression(2, *, 3))]
                $tokens = $root->stream->tokens;
                $test->assertCount(1, $tokens);
                $test->assertInstanceOf(TokenRegion::class, $tokens[0]);
                $test->assertSame('binaryExpression', $tokens[0]->name);

                $outer = $tokens[0]->stream->tokens;
                $test->assertSame('number', $outer[0]->name);
                $test->assertSame('plus', $outer[2]->name);
                $test->assertInstanceOf(TokenRegion::class, $outer[4]);
                $test->assertSame('binaryExpression', $outer[4]->name);

                $inner = $outer[4]->stream->tokens;
                $test->assertSame('number', $inner[0]->name);
                $test->assertSame('asterisk', $inner[2]->name);
                $test->assertSame('number', $inner[4]->name);
            },
            requireBofEof: false,
        );
    }

    #[Test]
    public function shouldRespectRightAssociativity(): void
    {
        // 2**3**2 → binaryExpression(2, **, binaryExpression(3, **, 2))
        $this->assertGrammarParsing(
            string: '2 ** 3 ** 2',
            grammar: (new MathExpression())->grammar(),
            assertTokenizationResultValid: function (TokenRegion $root, self $test): void {
                // $root IS the expression region — stream: [binaryExpression(2, **, binaryExpression(3, **, 2))]
                $tokens = $root->stream->tokens;
                $test->assertCount(1, $tokens);
                $test->assertInstanceOf(TokenRegion::class, $tokens[0]);
                $test->assertSame('binaryExpression', $tokens[0]->name);

                $outerTokens = $tokens[0]->stream->tokens;
                $test->assertSame('number', $outerTokens[0]->name);
                $test->assertSame('2', $outerTokens[0]->raw);
                $test->assertSame('caret', $outerTokens[2]->name);

                $innerRight = $outerTokens[4];
                $test->assertInstanceOf(TokenRegion::class, $innerRight);
                $test->assertSame('binaryExpression', $innerRight->name);

                $innerTokens = $innerRight->stream->tokens;
                $test->assertSame('3', $innerTokens[0]->raw);
                $test->assertSame('caret', $innerTokens[2]->name);
                $test->assertSame('2', $innerTokens[4]->raw);
            },
            requireBofEof: false,
        );
    }

    #[Test]
    public function shouldTreatParenGroupAsAtom(): void
    {
        // (1+2)*3 → binaryExpression(parenGroup(binaryExpression(1, +, 2)), *, 3)
        $this->assertGrammarParsing(
            string: '( 1 + 2 ) * 3',
            grammar: (new MathExpression())->grammar(),
            assertTokenizationResultValid: function (TokenRegion $root, self $test): void {
                // $root IS the expression region — stream: [binaryExpression(parenGroup(...), *, 3)]
                $tokens = $root->stream->tokens;
                $test->assertCount(1, $tokens);
                $test->assertInstanceOf(TokenRegion::class, $tokens[0]);
                $test->assertSame('binaryExpression', $tokens[0]->name);

                $outerTokens = $tokens[0]->stream->tokens;
                $test->assertInstanceOf(TokenRegion::class, $outerTokens[0]);
                $test->assertSame('parenGroup', $outerTokens[0]->name);
                $test->assertSame('asterisk', $outerTokens[2]->name);
                $test->assertSame('number', $outerTokens[4]->name);
            },
            requireBofEof: false,
        );
    }
}
