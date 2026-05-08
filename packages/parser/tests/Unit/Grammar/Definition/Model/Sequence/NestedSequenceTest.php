<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Unit\Grammar\Definition\Model\Sequence;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Cardinality;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Sequence\NestedSequence;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Sequence\SequenceNode;

#[Group('unit')]
final class NestedSequenceTest extends TestCase
{
    #[Test]
    public function shouldSetAllPropertiesThroughConstructor(): void
    {
        $node1 = new SequenceNode(['token1'], Cardinality::ExactlyOne);
        $node2 = new SequenceNode(['token2'], Cardinality::ExactlyOne);
        $alternativeSequences = [[$node1, $node2]];
        $cardinality = Cardinality::OneOrMore;
        $isLookahead = true;
        $isLookbehind = false;
        $tags = ['t', 's'];

        $nested = new NestedSequence($alternativeSequences, $cardinality, $isLookahead, $isLookbehind, $tags);

        self::assertSame($alternativeSequences, $nested->alternativeSequences);
        self::assertSame($cardinality, $nested->cardinality);
        self::assertTrue($nested->isLookahead);
        self::assertFalse($nested->isLookbehind);
        self::assertSame($tags, $nested->tags);
    }

    #[Test]
    public function shouldParseSimpleSequenceWhenFromString(): void
    {
        $nested = NestedSequence::fromString('(token1 token2)');

        self::assertCount(1, $nested->alternativeSequences);
        self::assertCount(2, $nested->alternativeSequences[0]);
        self::assertSame(Cardinality::ExactlyOne, $nested->cardinality);
        self::assertFalse($nested->isLookahead);
        self::assertFalse($nested->isLookbehind);
    }

    #[Test]
    public function shouldParseSequenceWithOneOrMoreQuantifierWhenFromString(): void
    {
        $nested = NestedSequence::fromString('(token)+');

        self::assertSame(Cardinality::OneOrMore, $nested->cardinality);
    }

    #[Test]
    public function shouldParseSequenceWithZeroOrMoreQuantifierWhenFromString(): void
    {
        $nested = NestedSequence::fromString('(token)*');

        self::assertSame(Cardinality::ZeroOrMore, $nested->cardinality);
    }

    #[Test]
    public function shouldParseSequenceWithZeroOrOneQuantifierWhenFromString(): void
    {
        $nested = NestedSequence::fromString('?(token)');

        self::assertSame(Cardinality::ZeroOrOne, $nested->cardinality);
    }

    #[Test]
    public function shouldParseSequenceWithUnionWhenFromString(): void
    {
        $nested = NestedSequence::fromString('(token1)|(token2)');

        self::assertCount(2, $nested->alternativeSequences);
        self::assertCount(1, $nested->alternativeSequences[0]);
        self::assertCount(1, $nested->alternativeSequences[1]);
    }

    #[Test]
    public function shouldParseSequenceWithLookaheadWhenFromString(): void
    {
        $nested = NestedSequence::fromString('>(token)');

        self::assertTrue($nested->isLookahead);
        self::assertFalse($nested->isLookbehind);
    }

    #[Test]
    public function shouldParseSequenceWithLookbehindWhenFromString(): void
    {
        $nested = NestedSequence::fromString('<(token)');

        self::assertFalse($nested->isLookahead);
        self::assertTrue($nested->isLookbehind);
    }

    #[Test]
    public function shouldParseSequenceWithTagsWhenFromString(): void
    {
        $nested = NestedSequence::fromString('(token)/ts');

        self::assertSame(['t', 's'], $nested->tags);
    }

    #[Test]
    public function shouldThrowExceptionWhenBothLookaheadAndLookbehind(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid nested sequence: `<>(token)`');

        NestedSequence::fromString('<>(token)');
    }

    #[Test]
    public function shouldReturnCorrectMinMembersNumberWhenGetMinMembersNumber(): void
    {
        $nested = NestedSequence::fromString('(token1 token2)');

        self::assertSame(2, $nested->getMinMembersNumber());
    }

    #[Test]
    public function shouldReturnCorrectMinMembersNumberForOptionalNodesWhenGetMinMembersNumber(): void
    {
        $nested = NestedSequence::fromString('(token1 ?token2)');

        self::assertSame(1, $nested->getMinMembersNumber());
    }

    #[Test]
    public function shouldReturnAllNodeNamesWhenGetAllNodeNames(): void
    {
        $nested = NestedSequence::fromString('(token1 token2)');

        $names = $nested->getAllNodeNames();

        self::assertContains('token1', $names);
        self::assertContains('token2', $names);
    }

    #[Test]
    public function shouldReturnFirstValidNodeNamesWhenGetFirstValidNodeNodeNames(): void
    {
        $nested = NestedSequence::fromString('(token1 token2)');

        $names = $nested->getFirstValidNodeNodeNames();

        self::assertContains('token1', $names);
        self::assertNotContains('token2', $names);
    }

    #[Test]
    public function shouldReturnFirstValidNodeNamesIncludingOptionalWhenGetFirstValidNodeNodeNames(): void
    {
        $nested = NestedSequence::fromString('(?token1 token2)');

        $names = $nested->getFirstValidNodeNodeNames();

        self::assertContains('token1', $names);
        self::assertContains('token2', $names);
    }

    #[Test]
    public function shouldReconstructStringWhenToString(): void
    {
        $input = '(token1 token2)+';
        $nested = NestedSequence::fromString($input);

        self::assertSame($input, $nested->toString());
    }

    #[Test]
    public function shouldReconstructStringWithUnionWhenToString(): void
    {
        $input = '(token1)|(token2)';
        $nested = NestedSequence::fromString($input);

        self::assertSame($input, $nested->toString());
    }

    #[Test]
    public function shouldReconstructStringWithLookaheadWhenToString(): void
    {
        $input = '>(token)';
        $nested = NestedSequence::fromString($input);

        self::assertSame($input, $nested->toString());
    }

    #[Test]
    public function shouldReconstructStringWithTagsWhenToString(): void
    {
        $input = '(token)/ts';
        $nested = NestedSequence::fromString($input);

        self::assertSame($input, $nested->toString());
    }
}
