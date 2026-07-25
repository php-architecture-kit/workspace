<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Unit\Foundation\Parsing\Model;

use LogicException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeAttributeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\GroupNode;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeOrigin;

#[Group('unit')]
final class GroupNodeTest extends TestCase
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
    public function getByNameReturnsMatchingChildren(): void
    {
        $ws1 = self::attr('ws');
        $ws2 = self::attr('ws');
        $node = new GroupNode('trivia', NodeOrigin::Region, [$ws1, self::attr('comma'), $ws2], null);

        $this->assertSame([$ws1, $ws2], $node->getByName('ws'));
        $this->assertSame([], $node->getByName('missing'));
    }

    #[Test]
    public function removeAttributeByIdentity(): void
    {
        $a = self::attr('a');
        $b = self::attr('b');
        $c = self::attr('c');
        $node = new GroupNode('g', NodeOrigin::Region, [$a, $b, $c], null);

        $node->removeAttribute($b);

        $this->assertSame([$a, $c], array_values($node->getAttributes()));
    }

    #[Test]
    public function replaceAttributePreservesPosition(): void
    {
        $a = self::attr('a');
        $b = self::attr('b');
        $c = self::attr('c');
        $d = self::attr('d');
        $node = new GroupNode('g', NodeOrigin::Region, [$a, $b, $c], null);

        $node->replaceAttribute($b, $d);

        $this->assertSame([$a, $d, $c], array_values($node->getAttributes()));
    }

    #[Test]
    public function removeThrowsForNonChild(): void
    {
        $node = new GroupNode('g', NodeOrigin::Region, [self::attr('a')], null);

        $this->expectException(LogicException::class);
        $node->removeAttribute(self::attr('stranger'));
    }
}
