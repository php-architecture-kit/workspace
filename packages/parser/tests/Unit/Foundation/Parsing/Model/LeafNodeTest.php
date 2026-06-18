<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Unit\Foundation\Parsing\Model;

use LogicException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeAttributeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\LeafNode;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeOrigin;

#[Group('unit')]
final class LeafNodeTest extends TestCase
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

    #[Test]
    public function acceptsExactlyOneAttribute(): void
    {
        $node = new LeafNode('space', NodeOrigin::Token, [], null);
        $attr = self::attr('raw');

        $node->addAttribute($attr);

        $this->assertCount(1, $node->getAttributes());
        $this->assertSame($attr, $node->getAttribute());
    }

    #[Test]
    public function rejectsASecondAttribute(): void
    {
        $node = new LeafNode('space', NodeOrigin::Token, [], null);
        $node->addAttribute(self::attr('raw'));

        $this->expectException(LogicException::class);
        $node->addAttribute(self::attr('extra'));
    }

    #[Test]
    public function getAttributeThrowsWhenEmpty(): void
    {
        $node = new LeafNode('space', NodeOrigin::Token, [], null);

        $this->expectException(LogicException::class);
        $node->getAttribute();
    }

    #[Test]
    public function replaceSwapsTheSingleAttribute(): void
    {
        $node = new LeafNode('space', NodeOrigin::Token, [], null);
        $first = self::attr('first');
        $second = self::attr('second');

        $node->addAttribute($first);
        $node->replaceAttribute($second);

        $this->assertSame($second, $node->getAttribute());
        $this->assertCount(1, $node->getAttributes());
    }
}
