<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Func\Grammar\TreeSchema;

use PhpArchitecture\Parser\Foundation\Grammar\Compiled\GrammarCompiler;
use PhpArchitecture\Parser\Foundation\Grammar\Compiled\Model\CompiledGrammar;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\SequenceValidityCursor;
use PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Json\JsonRfc8259;
use PhpArchitecture\Parser\Infrastructure\TreeSchema\Model\Json\Rfc8259\ArrayNode;
use PhpArchitecture\Parser\Infrastructure\TreeSchema\Model\Json\Rfc8259\JsonNode;
use PhpArchitecture\Parser\Infrastructure\TreeSchema\Model\Json\Rfc8259\MemberNode;
use PhpArchitecture\Parser\Infrastructure\TreeSchema\Model\Json\Rfc8259\ObjectNode;
use PhpArchitecture\Parser\Infrastructure\TreeSchema\Model\Json\Rfc8259\PrimitiveNode;
use PhpArchitecture\Parser\Infrastructure\TreeSchema\Model\Json\Rfc8259\PrimitiveType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('func')]
final class JsonRfc8259FacadeBuildTest extends TestCase
{
    private static ?CompiledGrammar $grammar = null;

    private static function grammar(): CompiledGrammar
    {
        return self::$grammar ??= (new GrammarCompiler())->compile((new JsonRfc8259())->grammar());
    }

    private function object(): ObjectNode
    {
        $g = self::grammar();
        return ObjectNode::create()->withMembersValidation(
            SequenceValidityCursor::fromSequence(
                $g->regions['object']->sequenceLibrary->rootSequence,
                'members',
            ),
        );
    }

    private function array(): ArrayNode
    {
        $g = self::grammar();
        return ArrayNode::create()->withItemsValidation(
            SequenceValidityCursor::fromSequence(
                $g->regions['array']->sequenceLibrary->rootSequence,
                'items',
            ),
        );
    }

    private function str(string $v): PrimitiveNode
    {
        return PrimitiveNode::create()->setPrimitive(PrimitiveType::String, $v);
    }

    private function num(string $v): PrimitiveNode
    {
        return PrimitiveNode::create()->setPrimitive(PrimitiveType::Number, $v);
    }

    private function json(ObjectNode|ArrayNode|PrimitiveNode $value): string
    {
        if ($value instanceof PrimitiveNode) {
            return (string) $value;
        }
        return (string) JsonNode::create()->setNodeValue($value);
    }

    // -------------------------------------------------------------------------
    // Primitives
    // -------------------------------------------------------------------------

    #[Test]
    public function buildStringPrimitive(): void
    {
        $this->assertSame('hello', json_decode($this->json($this->str('hello'))));
    }

    #[Test]
    public function buildNumberPrimitive(): void
    {
        $this->assertSame(42, json_decode($this->json($this->num('42'))));
    }

    #[Test]
    public function buildTruePrimitive(): void
    {
        $node = PrimitiveNode::create()->setPrimitive(PrimitiveType::True);
        $this->assertTrue(json_decode($this->json($node)));
    }

    #[Test]
    public function buildFalsePrimitive(): void
    {
        $node = PrimitiveNode::create()->setPrimitive(PrimitiveType::False);
        $this->assertFalse(json_decode($this->json($node)));
    }

    #[Test]
    public function buildNullPrimitive(): void
    {
        $node = PrimitiveNode::create()->setPrimitive(PrimitiveType::Null);
        $this->assertNull(json_decode($this->json($node)));
    }

    // -------------------------------------------------------------------------
    // Object
    // -------------------------------------------------------------------------

    #[Test]
    public function buildEmptyObject(): void
    {
        $decoded = json_decode($this->json($this->object()));
        $this->assertIsObject($decoded);
        $this->assertEmpty((array) $decoded);
    }

    #[Test]
    public function buildObjectWithOneMember(): void
    {
        $obj = $this->object()->addMember(
            MemberNode::create('name')->setNodeValue($this->str('Alice')),
        );

        $decoded = json_decode($this->json($obj));
        $this->assertSame('Alice', $decoded->name);
    }

    #[Test]
    public function buildObjectWithMultipleMembers(): void
    {
        $obj = $this->object()
            ->addMember(MemberNode::create('x')->setNodeValue($this->num('1')))
            ->addMember(MemberNode::create('y')->setNodeValue($this->num('2')))
            ->addMember(MemberNode::create('active')->setNodeValue(
                PrimitiveNode::create()->setPrimitive(PrimitiveType::True),
            ));

        $decoded = json_decode($this->json($obj));
        $this->assertSame(1, $decoded->x);
        $this->assertSame(2, $decoded->y);
        $this->assertTrue($decoded->active);
    }

    // -------------------------------------------------------------------------
    // Array
    // -------------------------------------------------------------------------

    #[Test]
    public function buildEmptyArray(): void
    {
        $decoded = json_decode($this->json($this->array()));
        $this->assertIsArray($decoded);
        $this->assertEmpty($decoded);
    }

    #[Test]
    public function buildArrayWithPrimitiveItems(): void
    {
        $arr = $this->array()
            ->addItem($this->num('1'))
            ->addItem($this->num('2'))
            ->addItem($this->num('3'));

        $this->assertSame([1, 2, 3], json_decode($this->json($arr)));
    }

    #[Test]
    public function buildArrayWithMixedItems(): void
    {
        $arr = $this->array()
            ->addItem($this->str('hello'))
            ->addItem(PrimitiveNode::create()->setPrimitive(PrimitiveType::True))
            ->addItem(PrimitiveNode::create()->setPrimitive(PrimitiveType::Null));

        $this->assertSame(['hello', true, null], json_decode($this->json($arr)));
    }

    // -------------------------------------------------------------------------
    // Nested structures
    // -------------------------------------------------------------------------

    #[Test]
    public function buildNestedObject(): void
    {
        $inner = $this->object()->addMember(
            MemberNode::create('value')->setNodeValue($this->num('99')),
        );
        $outer = $this->object()->addMember(
            MemberNode::create('nested')->setNodeValue($inner),
        );

        $decoded = json_decode($this->json($outer));
        $this->assertSame(99, $decoded->nested->value);
    }

    #[Test]
    public function buildObjectWithArrayMember(): void
    {
        $tags = $this->array()
            ->addItem($this->str('php'))
            ->addItem($this->str('json'));

        $obj = $this->object()->addMember(
            MemberNode::create('tags')->setNodeValue($tags),
        );

        $decoded = json_decode($this->json($obj));
        $this->assertSame(['php', 'json'], $decoded->tags);
    }

    #[Test]
    public function buildComplexDocument(): void
    {
        $address = $this->object()
            ->addMember(MemberNode::create('street')->setNodeValue($this->str('123 Main St')))
            ->addMember(MemberNode::create('zip')->setNodeValue($this->str('00-001')));

        $hobbies = $this->array()
            ->addItem($this->str('coding'))
            ->addItem($this->str('reading'));

        $person = $this->object()
            ->addMember(MemberNode::create('name')->setNodeValue($this->str('Alice')))
            ->addMember(MemberNode::create('age')->setNodeValue($this->num('30')))
            ->addMember(MemberNode::create('active')->setNodeValue(
                PrimitiveNode::create()->setPrimitive(PrimitiveType::True),
            ))
            ->addMember(MemberNode::create('address')->setNodeValue($address))
            ->addMember(MemberNode::create('hobbies')->setNodeValue($hobbies));

        $json = $this->json($person);
        $decoded = json_decode($json, true);
        $this->assertNotNull($decoded, 'json_decode must succeed: ' . $json);
        $this->assertSame('Alice', $decoded['name']);
        $this->assertSame(30, $decoded['age']);
        $this->assertTrue($decoded['active']);
        $this->assertSame('123 Main St', $decoded['address']['street']);
        $this->assertSame('00-001', $decoded['address']['zip']);
        $this->assertSame(['coding', 'reading'], $decoded['hobbies']);
    }

    // -------------------------------------------------------------------------
    // Mutations
    // -------------------------------------------------------------------------

    #[Test]
    public function removeMemberAndSerialize(): void
    {
        $obj = $this->object()
            ->addMember(MemberNode::create('keep')->setNodeValue($this->str('yes')))
            ->addMember(MemberNode::create('remove')->setNodeValue($this->num('0')));

        $obj->removeMemberByIndex(1);

        $decoded = json_decode($this->json($obj), true);
        $this->assertArrayHasKey('keep', $decoded);
        $this->assertArrayNotHasKey('remove', $decoded);
    }

    #[Test]
    public function removeArrayItemAndSerialize(): void
    {
        $arr = $this->array()
            ->addItem($this->num('1'))
            ->addItem($this->num('2'))
            ->addItem($this->num('3'));

        $arr->removeItemByIndex(1);

        $this->assertSame([1, 3], json_decode($this->json($arr)));
    }

    #[Test]
    public function getMembersReturnsCorrectNodes(): void
    {
        $obj = $this->object()
            ->addMember(MemberNode::create('a')->setNodeValue($this->num('1')))
            ->addMember(MemberNode::create('b')->setNodeValue($this->num('2')));

        $members = $obj->getMembers();
        $this->assertCount(2, $members);
        $this->assertContainsOnlyInstancesOf(MemberNode::class, $members);
        $this->assertSame('a', $members[0]->getRawIdentifier());
        $this->assertSame('b', $members[1]->getRawIdentifier());
    }

    #[Test]
    public function getItemsReturnsCorrectNodes(): void
    {
        $arr = $this->array()
            ->addItem($this->str('x'))
            ->addItem($this->num('7'));

        $items = $arr->getItems();
        $this->assertCount(2, $items);
        $this->assertInstanceOf(PrimitiveNode::class, $items[0]);
        $this->assertInstanceOf(PrimitiveNode::class, $items[1]);
    }
}
