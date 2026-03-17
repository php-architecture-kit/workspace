<?php

declare(strict_types=1);

namespace Tests\PhpArchitecture\Parser\Unit\Model\Grammar\Rules;

use InvalidArgumentException;
use PhpArchitecture\Parser\Model\Grammar\Rules\Cardinality;
use PhpArchitecture\Parser\Model\Grammar\Rules\SequenceNode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SequenceNodeTest extends TestCase
{
    #[Test]
    #[DataProvider('provideValidSequencesFromFile')]
    public function fromStringCreatesValidInstanceForAllValidSequences(string $sequence): void
    {
        $node = SequenceNode::fromString($sequence);

        $this->assertInstanceOf(SequenceNode::class, $node);
        $this->assertSame($sequence, $node->toString());
    }

    #[Test]
    public function basicNodeWithoutModifiers(): void
    {
        $node = SequenceNode::fromString('token');

        $this->assertSame(['token'], $node->alternatives);
        $this->assertSame(Cardinality::ExactlyOne, $node->cardinality);
        $this->assertFalse($node->isLookahead);
        $this->assertFalse($node->isLookbehind);
        $this->assertNull($node->anchorName);
        $this->assertSame([], $node->tags);
    }

    #[Test]
    public function nodeWithAlternatives(): void
    {
        $node = SequenceNode::fromString('token|member|value');

        $this->assertSame(['token', 'member', 'value'], $node->alternatives);
        $this->assertSame(Cardinality::ExactlyOne, $node->cardinality);
    }

    #[Test]
    public function optionalNode(): void
    {
        $node = SequenceNode::fromString('?token');

        $this->assertSame(['token'], $node->alternatives);
        $this->assertSame(Cardinality::ZeroOrOne, $node->cardinality);
    }

    #[Test]
    public function zeroOrMoreNode(): void
    {
        $node = SequenceNode::fromString('token*');

        $this->assertSame(['token'], $node->alternatives);
        $this->assertSame(Cardinality::ZeroOrMore, $node->cardinality);
    }

    #[Test]
    public function oneOrMoreNode(): void
    {
        $node = SequenceNode::fromString('token+');

        $this->assertSame(['token'], $node->alternatives);
        $this->assertSame(Cardinality::OneOrMore, $node->cardinality);
    }

    #[Test]
    public function lookaheadNode(): void
    {
        $node = SequenceNode::fromString('>token');

        $this->assertSame(['token'], $node->alternatives);
        $this->assertTrue($node->isLookahead);
        $this->assertFalse($node->isLookbehind);
    }

    #[Test]
    public function lookbehindNode(): void
    {
        $node = SequenceNode::fromString('<token');

        $this->assertSame(['token'], $node->alternatives);
        $this->assertFalse($node->isLookahead);
        $this->assertTrue($node->isLookbehind);
    }

    #[Test]
    public function nodeWithAnchorName(): void
    {
        $node = SequenceNode::fromString('token[anchor]');

        $this->assertSame(['token'], $node->alternatives);
        $this->assertSame('anchor', $node->anchorName);
        $this->assertSame([], $node->tags);
    }

    #[Test]
    public function nodeWithSingleTag(): void
    {
        $node = SequenceNode::fromString('token/s');

        $this->assertSame(['token'], $node->alternatives);
        $this->assertNull($node->anchorName);
        $this->assertSame(['s'], $node->tags);
    }

    #[Test]
    public function nodeWithMultipleTags(): void
    {
        $node = SequenceNode::fromString('token/abc');

        $this->assertSame(['token'], $node->alternatives);
        $this->assertSame(['a', 'b', 'c'], $node->tags);
    }

    #[Test]
    public function nodeWithAnchorAndTags(): void
    {
        $node = SequenceNode::fromString('token[anchor]/st');

        $this->assertSame(['token'], $node->alternatives);
        $this->assertSame('anchor', $node->anchorName);
        $this->assertSame(['s', 't'], $node->tags);
    }

    #[Test]
    public function nodeWithQuantifierAnchorAndTags(): void
    {
        $node = SequenceNode::fromString('token+[anchor]/s');

        $this->assertSame(['token'], $node->alternatives);
        $this->assertSame(Cardinality::OneOrMore, $node->cardinality);
        $this->assertSame('anchor', $node->anchorName);
        $this->assertSame(['s'], $node->tags);
    }

    #[Test]
    public function optionalNodeWithAnchorAndTags(): void
    {
        $node = SequenceNode::fromString('?token[opt]/s');

        $this->assertSame(['token'], $node->alternatives);
        $this->assertSame(Cardinality::ZeroOrOne, $node->cardinality);
        $this->assertSame('opt', $node->anchorName);
        $this->assertSame(['s'], $node->tags);
    }

    #[Test]
    public function lookaheadWithAnchor(): void
    {
        $node = SequenceNode::fromString('>token[ahead]');

        $this->assertTrue($node->isLookahead);
        $this->assertSame('ahead', $node->anchorName);
    }

    #[Test]
    public function lookbehindWithAnchor(): void
    {
        $node = SequenceNode::fromString('<token[behind]');

        $this->assertTrue($node->isLookbehind);
        $this->assertSame('behind', $node->anchorName);
    }

    #[Test]
    public function alternativesWithQuantifier(): void
    {
        $node = SequenceNode::fromString('identifier|keyword*');

        $this->assertSame(['identifier', 'keyword'], $node->alternatives);
        $this->assertSame(Cardinality::ZeroOrMore, $node->cardinality);
    }

    #[Test]
    public function alternativesWithAnchor(): void
    {
        $node = SequenceNode::fromString('identifier|keyword[name]');

        $this->assertSame(['identifier', 'keyword'], $node->alternatives);
        $this->assertSame('name', $node->anchorName);
    }

    #[Test]
    public function alternativesWithAnchorAndTags(): void
    {
        $node = SequenceNode::fromString('identifier|keyword[name]/t');

        $this->assertSame(['identifier', 'keyword'], $node->alternatives);
        $this->assertSame('name', $node->anchorName);
        $this->assertSame(['t'], $node->tags);
    }

    #[Test]
    public function toStringReconstructsOriginalSequence(): void
    {
        $sequences = [
            'token',
            'token|member',
            '?token',
            'token*',
            'token+',
            '>token',
            '<token',
            'token[anchor]',
            'token/s',
            'token[anchor]/st',
            'token+[anchor]/s',
            '?token[opt]/x',
            '>token[ahead]',
            '<token[behind]',
        ];

        foreach ($sequences as $sequence) {
            $node = SequenceNode::fromString($sequence);
            $this->assertSame($sequence, $node->toString());
        }
    }

    #[Test]
    public function getAllNodeNamesReturnsAllAlternatives(): void
    {
        $node = SequenceNode::fromString('token|member|value');

        $this->assertSame(['token', 'member', 'value'], $node->getAllNodeNames());
    }

    #[Test]
    public function getAllNodeNamesReturnsSingleNodeForNonUnion(): void
    {
        $node = SequenceNode::fromString('token');

        $this->assertSame(['token'], $node->getAllNodeNames());
    }

    #[Test]
    public function throwsExceptionForInvalidSequenceNode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SequenceNode::fromString('');
    }

    #[Test]
    public function throwsExceptionForBothLookaheadAndLookbehind(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Lookahead and lookbehind are not allowed to be used at the same time');
        SequenceNode::fromString('><token');
    }

    #[Test]
    public function throwsExceptionForLookaheadWithRepeatingQuantifier(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Lookahead and lookbehind are not allowed to be repeated');
        SequenceNode::fromString('>token+');
    }

    #[Test]
    public function throwsExceptionForLookbehindWithRepeatingQuantifier(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Lookahead and lookbehind are not allowed to be repeated');
        SequenceNode::fromString('<token*');
    }

    #[Test]
    public function assertSequenceMatchAnchorRequirementsValidatesLookahead(): void
    {
        $original = SequenceNode::fromString('>token');
        $anchor = SequenceNode::fromString('-');

        $this->expectNotToPerformAssertions();
        $anchor->assertSequenceMatchAnchorRequirements($original);
    }

    #[Test]
    public function assertSequenceMatchAnchorRequirementsThrowsForInvalidAnchor(): void
    {
        $original = SequenceNode::fromString('token');
        $anchor = SequenceNode::fromString('>invalid');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Lookahead and lookbehind are not allowed to be used in anchor sequence');
        $anchor->assertSequenceMatchAnchorRequirements($original);
    }

    public static function provideValidSequencesFromFile(): array
    {
        $filePath = __DIR__ . '/../../../../valid_sequences.txt';
        $content = file_get_contents($filePath);
        $lines = array_filter(
            array_map('trim', explode("\n", $content)),
            fn($line) => $line !== '' && !str_contains($line, ' ') && !str_contains($line, '(')
        );

        $data = [];
        foreach ($lines as $line) {
            $data[$line] = [$line];
        }

        return $data;
    }
}
