<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Unit\Foundation\Grammar\Definition\Model\Sequence;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Cardinality;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Sequence\SequenceNode;

#[Group('unit')]
final class SequenceNodeTest extends TestCase
{
    #[Test]
    public function shouldSetAllPropertiesThroughConstructor(): void
    {
        $alternatives = ['token1', 'token2'];
        $cardinality = Cardinality::OneOrMore;
        $isLookahead = true;
        $isLookbehind = false;
        $anchorName = 'anchor';
        $tags = ['t', 's'];

        $node = new SequenceNode($alternatives, $cardinality, $isLookahead, $isLookbehind, $anchorName, $tags);

        self::assertSame($alternatives, $node->alternatives);
        self::assertSame($cardinality, $node->cardinality);
        self::assertTrue($node->isLookahead);
        self::assertFalse($node->isLookbehind);
        self::assertSame($anchorName, $node->anchorName);
        self::assertSame($tags, $node->tags);
    }

    #[Test]
    public function shouldParseSimpleTokenWhenFromString(): void
    {
        $node = SequenceNode::fromString('token');

        self::assertSame(['token'], $node->alternatives);
        self::assertSame(Cardinality::ExactlyOne, $node->cardinality);
        self::assertFalse($node->isLookahead);
        self::assertFalse($node->isLookbehind);
        self::assertNull($node->anchorName);
        self::assertEmpty($node->tags);
    }

    #[Test]
    public function shouldParseTokenWithOneOrMoreQuantifierWhenFromString(): void
    {
        $node = SequenceNode::fromString('token+');

        self::assertSame(['token'], $node->alternatives);
        self::assertSame(Cardinality::OneOrMore, $node->cardinality);
    }

    #[Test]
    public function shouldParseTokenWithZeroOrMoreQuantifierWhenFromString(): void
    {
        $node = SequenceNode::fromString('token*');

        self::assertSame(['token'], $node->alternatives);
        self::assertSame(Cardinality::ZeroOrMore, $node->cardinality);
    }

    #[Test]
    public function shouldParseTokenWithZeroOrOneQuantifierWhenFromString(): void
    {
        $node = SequenceNode::fromString('?token');

        self::assertSame(['token'], $node->alternatives);
        self::assertSame(Cardinality::ZeroOrOne, $node->cardinality);
    }

    #[Test]
    public function shouldParseTokenWithLookaheadWhenFromString(): void
    {
        $node = SequenceNode::fromString('>token');

        self::assertSame(['token'], $node->alternatives);
        self::assertTrue($node->isLookahead);
        self::assertFalse($node->isLookbehind);
    }

    #[Test]
    public function shouldParseTokenWithLookbehindWhenFromString(): void
    {
        $node = SequenceNode::fromString('<token');

        self::assertSame(['token'], $node->alternatives);
        self::assertFalse($node->isLookahead);
        self::assertTrue($node->isLookbehind);
    }

    #[Test]
    public function shouldParseTokenWithAnchorWhenFromString(): void
    {
        $node = SequenceNode::fromString('token[anchorName]');

        self::assertSame(['token'], $node->alternatives);
        self::assertSame('anchorName', $node->anchorName);
    }

    #[Test]
    public function shouldParseTokenWithTagsWhenFromString(): void
    {
        $node = SequenceNode::fromString('token/ts');

        self::assertSame(['token'], $node->alternatives);
        self::assertSame(['t', 's'], $node->tags);
    }

    #[Test]
    public function shouldParseUnionAlternativesWhenFromString(): void
    {
        $node = SequenceNode::fromString('token1|token2|token3');

        self::assertSame(['token1', 'token2', 'token3'], $node->alternatives);
    }

    #[Test]
    public function shouldThrowExceptionWhenBothLookaheadAndLookbehind(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid sequence node: `<>token`');

        SequenceNode::fromString('<>token');
    }

    #[Test]
    public function shouldThrowExceptionWhenLookaheadWithOneOrMoreQuantifier(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Lookahead and lookbehind are not allowed to be repeated');

        SequenceNode::fromString('>token+');
    }

    #[Test]
    public function shouldReturnAlternativesWhenGetAllNodeNames(): void
    {
        $node = SequenceNode::fromString('token1|token2');

        self::assertSame(['token1', 'token2'], $node->getAllNodeNames());
    }

    #[Test]
    public function shouldReconstructStringWhenToString(): void
    {
        $input = 'token+[anchor]/ts';
        $node = SequenceNode::fromString($input);

        self::assertSame($input, $node->toString());
    }

    #[Test]
    public function shouldReconstructStringWithLookaheadWhenToString(): void
    {
        $input = '>token';
        $node = SequenceNode::fromString($input);

        self::assertSame($input, $node->toString());
    }

    #[Test]
    public function shouldReconstructStringWithZeroOrMoreWhenToString(): void
    {
        $input = 'token*';
        $node = SequenceNode::fromString($input);

        self::assertSame($input, $node->toString());
    }
}
