<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Unit\Foundation\Parsing\Navigation;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\NodeAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawContentAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\GroupNode;
use PhpArchitecture\Parser\Foundation\Parsing\Model\LeafNode;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeOrigin;
use PhpArchitecture\Parser\Foundation\ParsedTree\Navigation\TreeNavigator;

#[Group('unit')]
final class TreeNavigatorTest extends TestCase
{
    /**
     * Tree:
     *   root (group)
     *   ├─ rawA "a"
     *   ├─ childAttr -> child (leaf)
     *   │                └─ rawB "b"
     *   └─ rawC "c"
     * Document order: rawA, childAttr, rawB, rawC
     */
    private function buildTree(): array
    {
        $rawA  = new RawContentAttribute('a');
        $rawB  = new RawContentAttribute('b');
        $rawC  = new RawContentAttribute('c');

        $child     = new LeafNode('child', NodeOrigin::Region, [$rawB], null);
        $childAttr = NodeAttribute::fromNode($child);

        $root = new GroupNode('root', NodeOrigin::Region, [$rawA, $childAttr, $rawC], null);

        return compact('root', 'rawA', 'rawB', 'rawC', 'childAttr');
    }

    #[Test]
    public function flattensAttributesInDocumentOrder(): void
    {
        ['root' => $root, 'rawA' => $rawA, 'rawB' => $rawB, 'rawC' => $rawC, 'childAttr' => $childAttr] = $this->buildTree();

        $order = (new TreeNavigator($root))->attributesInDocumentOrder();

        $this->assertSame([$rawA, $childAttr, $rawB, $rawC], $order);
    }

    #[Test]
    public function previousAttributeCrossesNodeBoundaries(): void
    {
        ['root' => $root, 'rawB' => $rawB, 'rawC' => $rawC, 'childAttr' => $childAttr] = $this->buildTree();
        $nav = new TreeNavigator($root);

        $this->assertSame($childAttr, $nav->previousAttribute($rawB));
        $this->assertSame($rawB, $nav->previousAttribute($rawC));
    }

    #[Test]
    public function nextAttributeCrossesNodeBoundaries(): void
    {
        ['root' => $root, 'rawB' => $rawB, 'rawC' => $rawC, 'childAttr' => $childAttr] = $this->buildTree();
        $nav = new TreeNavigator($root);

        $this->assertSame($rawB, $nav->nextAttribute($childAttr));
        $this->assertSame($rawC, $nav->nextAttribute($rawB));
    }

    #[Test]
    public function nullAtTreeBoundaries(): void
    {
        ['root' => $root, 'rawA' => $rawA, 'rawC' => $rawC] = $this->buildTree();
        $nav = new TreeNavigator($root);

        $this->assertNull($nav->previousAttribute($rawA));
        $this->assertNull($nav->nextAttribute($rawC));
    }
}
