<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Unit\Foundation\Parsing\Model\Attribute;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PhpArchitecture\Parser\Foundation\Matching\Model\NestedSequence;
use PhpArchitecture\Parser\Foundation\Matching\Model\SequenceNode;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeAttributeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\GroupedAttribute;

#[Group('unit')]
final class GroupedAttributeTest extends TestCase
{
    private static function attr(string $name): NodeAttributeInterface
    {
        return new class($name) implements NodeAttributeInterface {
            public function __construct(private readonly string $name) {}
            public function getName(): string { return $this->name; }
            public function withParent(NodeInterface $parent): static { return $this; }
            public function __toString(): string { return $this->name; }
        };
    }

    private static function seq(array $alternative, int $min = 1, int $max = 1): NestedSequence
    {
        return new NestedSequence([$alternative], $min, $max);
    }

    private static function node(string $name, int $min = 1): SequenceNode
    {
        return new SequenceNode([$name], $min, PHP_INT_MAX);
    }

    // -------------------------------------------------------------------------

    #[Test]
    public function withValidSequence_onEmptyGrouped_startsAtPositionZero(): void
    {
        $grouped = new GroupedAttribute('members', null);
        $grouped->withValidSequence(self::seq([
            self::node('A'),
            self::node('B'),
        ]));

        // Next must be A (cursor at position 0)
        $this->expectException(InvalidArgumentException::class);
        $grouped->addAttribute(self::attr('B'));
    }

    #[Test]
    public function withValidSequence_withExistingAttributes_replaysAndAdvancesCursor(): void
    {
        // GroupedAttribute already has 'member' added (e.g., built from AST)
        $grouped = new GroupedAttribute('members', null, [self::attr('member')]);

        // Attach cursor AFTER the attribute was added
        $grouped->withValidSequence(self::seq([
            self::node('member'),
            self::node('comma'),
            self::node('member'),
        ]));

        // Cursor should be at position 1 (past 'member'), so 'comma' is next
        $grouped->addAttribute(self::attr('comma')); // must not throw
        $grouped->addAttribute(self::attr('member')); // must not throw

        self::assertCount(3, $grouped->attributes);
    }

    #[Test]
    public function withValidSequence_withExistingAttributesInWrongOrder_throws(): void
    {
        // GroupedAttribute built incorrectly — 'comma' before 'member'
        $grouped = new GroupedAttribute('members', null, [self::attr('comma')]);

        $this->expectException(InvalidArgumentException::class);
        $grouped->withValidSequence(self::seq([
            self::node('member'),
            self::node('comma'),
        ]));
    }

    #[Test]
    public function withValidSequence_calledTwice_resetsAndReplays(): void
    {
        $grouped = new GroupedAttribute('members', null, [self::attr('A')]);

        $seq = self::seq([self::node('A'), self::node('B')]);

        $grouped->withValidSequence($seq);
        // Cursor is now past A, next is B
        $grouped->addAttribute(self::attr('B'));

        // Re-attach cursor — should replay both A and B
        $grouped->withValidSequence($seq);
        // After replay of [A, B], sequence is complete — adding C should throw
        $this->expectException(InvalidArgumentException::class);
        $grouped->addAttribute(self::attr('C'));
    }
}
