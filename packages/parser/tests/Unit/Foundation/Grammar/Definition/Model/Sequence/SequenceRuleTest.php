<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Unit\Foundation\Grammar\Definition\Model\Sequence;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Sequence\SequenceNode;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Sequence\SequenceRule;

#[Group('unit')]
final class SequenceRuleTest extends TestCase
{
    #[Test]
    public function shouldSetNodesThroughConstructor(): void
    {
        $node1 = SequenceNode::fromString('token1');
        $node2 = SequenceNode::fromString('token2');
        $nodes = [$node1, $node2];

        $rule = new SequenceRule($nodes);

        self::assertSame($nodes, $rule->nodes);
    }

    #[Test]
    public function shouldParseSimpleSequenceWhenFromString(): void
    {
        $rule = SequenceRule::fromString('token1 token2');

        self::assertCount(2, $rule->nodes);
        self::assertInstanceOf(SequenceNode::class, $rule->nodes[0]);
        self::assertInstanceOf(SequenceNode::class, $rule->nodes[1]);
    }

    #[Test]
    public function shouldParseSequenceWithNestedSequencesWhenFromString(): void
    {
        $rule = SequenceRule::fromString('token1 (token2 token3)');

        self::assertCount(2, $rule->nodes);
        self::assertInstanceOf(SequenceNode::class, $rule->nodes[0]);
    }

    #[Test]
    public function shouldThrowExceptionWhenEmptySequence(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Sequence can't be empty");

        SequenceRule::fromString('');
    }

    #[Test]
    public function shouldThrowExceptionWhenForbiddenSubstringPlusBar(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Sequence contains forbidden substrings');

        SequenceRule::fromString('token+|other');
    }

    #[Test]
    public function shouldThrowExceptionWhenForbiddenSubstringAsteriskBar(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Sequence contains forbidden substrings');

        SequenceRule::fromString('token*|other');
    }

    #[Test]
    public function shouldThrowExceptionWhenForbiddenSubstringBarQuestion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Sequence contains forbidden substrings');

        SequenceRule::fromString('token|?other');
    }

    #[Test]
    public function shouldThrowExceptionWhenForbiddenSubstringBarLookbehind(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Sequence contains forbidden substrings');

        SequenceRule::fromString('token|<other');
    }

    #[Test]
    public function shouldThrowExceptionWhenForbiddenSubstringBarLookahead(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Sequence contains forbidden substrings');

        SequenceRule::fromString('token|>other');
    }

    #[Test]
    public function shouldThrowExceptionWhenForbiddenSubstringDoubleBar(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Sequence contains forbidden substrings');

        SequenceRule::fromString('token||other');
    }

    #[Test]
    public function shouldReturnAllNodeNamesWhenGetAllNodeNames(): void
    {
        $rule = SequenceRule::fromString('token1 token2');

        $names = $rule->getAllNodeNames();

        self::assertContains('token1', $names);
        self::assertContains('token2', $names);
        self::assertCount(2, $names);
    }

    #[Test]
    public function shouldReturnUniqueNodeNamesWhenGetAllNodeNames(): void
    {
        $rule = SequenceRule::fromString('token1 token1');

        $names = $rule->getAllNodeNames();

        self::assertCount(1, $names);
        self::assertContains('token1', $names);
    }

    #[Test]
    public function shouldReturnFirstValidNodeNamesWhenGetFirstValidNodeNodeNames(): void
    {
        $rule = SequenceRule::fromString('token1 token2');

        $names = $rule->getFirstValidNodeNodeNames();

        self::assertContains('token1', $names);
        self::assertNotContains('token2', $names);
    }

    #[Test]
    public function shouldReturnFirstValidNodeNamesIncludingOptionalWhenGetFirstValidNodeNodeNames(): void
    {
        $rule = SequenceRule::fromString('?token1 token2');

        $names = $rule->getFirstValidNodeNodeNames();

        self::assertContains('token1', $names);
        self::assertContains('token2', $names);
    }

    #[Test]
    public function shouldReconstructStringWhenToString(): void
    {
        $input = 'token1 token2+';
        $rule = SequenceRule::fromString($input);

        self::assertSame($input, $rule->toString());
    }

    #[Test]
    public function shouldReconstructStringWithNestedSequenceWhenToString(): void
    {
        $input = 'token1 (token2 token3)';
        $rule = SequenceRule::fromString($input);

        self::assertSame($input, $rule->toString());
    }
}
