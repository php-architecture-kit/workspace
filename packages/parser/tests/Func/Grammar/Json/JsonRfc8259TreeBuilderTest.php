<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Func\Grammar\Json;

use PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Json\JsonRfc8259;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Rfc8259\ArrayNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Rfc8259\MemberNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Rfc8259\ObjectNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Rfc8259\PrimitiveNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Rfc8259\PrimitiveType;
use PhpArchitecture\Parser\Tests\Func\Grammar\GrammarTestCase;
use PhpArchitecture\Parser\Tests\Func\Grammar\Json\Support\TestJsonFormat;
use PhpArchitecture\Parser\Tests\Func\Grammar\Json\Support\TestJsonTreeBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use InvalidArgumentException;
use stdClass;

/**
 * Demonstrates building, formatting, and editing a JSON document purely through
 * the generated ParsedTree classes (ObjectNode/ArrayNode/MemberNode/PrimitiveNode).
 * TestJsonTreeBuilder (tests/Support) is the reusable piece extracted from this
 * exploration; see its docblocks for the framework gaps it works around: lazy
 * trivia auto-factories (no built-in pretty-printer), addMember()/addItem()
 * rejecting a parsed (not built) tree, content inserted into the tree verbatim
 * (must be escaped by the caller), and — see the "format() must be idempotent"
 * section below — a parsed object/array keeping its leading indentation in a
 * different attribute than the one a naive formatter would think to touch.
 *
 * Grammar is plain Whitespace (not Indentation) — a prior attempt to switch
 * JsonRfc8259 to extend Indentation was reverted.
 */
#[Group('func')]
final class JsonRfc8259TreeBuilderTest extends GrammarTestCase
{
    private function grammar()
    {
        return (new JsonRfc8259())->grammar();
    }

    // -------------------------------------------------------------------------
    // Building from scratch, across all three formats
    // -------------------------------------------------------------------------

    #[Test]
    public function buildsMinifiedOutputMatchingJsonEncode(): void
    {
        $data = self::sampleData();

        $tree = TestJsonTreeBuilder::buildFormatted($data, TestJsonFormat::Minified);

        $this->assertSame(
            json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            (string) $tree,
        );
    }

    #[Test]
    public function buildsPretty4OutputMatchingJsonEncodePrettyPrint(): void
    {
        // PHP's own JSON_PRETTY_PRINT (4-space indent, space after colon, no
        // trailing whitespace) is used as an independent oracle here rather than
        // a hand-written expectation.
        $data = self::sampleData();

        $tree = TestJsonTreeBuilder::buildFormatted($data, TestJsonFormat::Pretty4);

        $this->assertSame(
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            (string) $tree,
        );
    }

    #[Test]
    public function buildsPretty2OutputWithTwoSpaceIndentation(): void
    {
        $data = [
            'a' => 1,
            'b' => [1, 2],
            'c' => ['nested' => true],
        ];

        $tree = TestJsonTreeBuilder::buildFormatted($data, TestJsonFormat::Pretty2);

        $expected = <<<JSON
        {
          "a": 1,
          "b": [
            1,
            2
          ],
          "c": {
            "nested": true
          }
        }
        JSON;

        $this->assertSame($expected, (string) $tree);
    }

    #[Test]
    public function emptyArraysAndObjectsStayCompactEvenWhenPrettyPrinted(): void
    {
        $tree = TestJsonTreeBuilder::buildFormatted(['a' => [], 'b' => new stdClass()], TestJsonFormat::Pretty4);

        $expected = <<<JSON
        {
            "a": [],
            "b": {}
        }
        JSON;

        $this->assertSame($expected, (string) $tree);
    }

    /**
     * Every value built via TestJsonTreeBuilder::build() must decode back to the exact
     * PHP value it was built from — the strongest, format-agnostic correctness
     * check available (stronger than eyeballing one literal string).
     */
    #[Test]
    public function builtTreeRoundTripsThroughJsonDecodeForAllFormats(): void
    {
        $data = self::sampleData();

        foreach ([TestJsonFormat::Minified, TestJsonFormat::Pretty2, TestJsonFormat::Pretty4] as $style) {
            $tree = TestJsonTreeBuilder::buildFormatted($data, $style);
            $this->assertSame(
                $data,
                json_decode((string) $tree, true, flags: JSON_THROW_ON_ERROR),
                "Round trip failed for style {$style->name}",
            );
        }
    }

    /** @return array<string,mixed> */
    private static function sampleData(): array
    {
        return [
            'name' => 'Widget "X"',
            'count' => 3,
            'price' => 19.99,
            'active' => true,
            'meta' => null,
            'tags' => ['red', 'blue'],
            'dimensions' => ['w' => 10, 'h' => 20],
            'variants' => [
                ['sku' => 'A1', 'inStock' => true],
                ['sku' => 'A2', 'inStock' => false],
            ],
            'empty' => [],
        ];
    }

    // -------------------------------------------------------------------------
    // format() must be idempotent and origin-agnostic (parsed tree vs built tree)
    //
    // First cut of TestJsonTreeBuilder::format() only cleared/rebuilt whitespace
    // inside ObjectNode::$members / ArrayNode::$items. That is enough for a tree
    // built via build() (whose members/items sequence is the *only* place with
    // whitespace), but a *parsed* pretty document turned out to keep (some of)
    // its leading indentation somewhere format() never looked: ObjectNode/
    // ArrayNode's own outer trivia0 (see withRootSequence() in JsonRfc8259 —
    // beginObject is followed by an outer `-t*` slot *before* the members
    // group's own inner `-l*` one). Reformatting such a tree duplicated the
    // indent instead of replacing it, and reformatting to Minified left stale
    // indentation behind. Fixed by having format() unconditionally clear both
    // ObjectNode/ArrayNode's own trivia0/trivia1 on every call, not just the
    // members/items sequence's internal ones — this fix is independent of, and
    // still needed regardless of, the `-t*`/`-l*` resolution fix below, since
    // real parsing can still split a run's text across the outer and inner slot
    // in either order depending on the document.
    // -------------------------------------------------------------------------

    #[Test]
    public function formattingTwiceInARowProducesIdenticalOutput(): void
    {
        $tree = TestJsonTreeBuilder::buildFormatted(['a' => 1, 'b' => ['x' => 1, 'y' => 2]], TestJsonFormat::Pretty4);
        $once = (string) $tree;

        TestJsonTreeBuilder::format($tree, TestJsonFormat::Pretty4);

        $this->assertSame($once, (string) $tree);
    }

    #[Test]
    public function reformattingAParsedMinifiedDocumentToPretty4MatchesBuildingItPrettyDirectly(): void
    {
        $expected = (string) TestJsonTreeBuilder::buildFormatted(['a' => 1, 'b' => ['x' => 1, 'y' => 2]], TestJsonFormat::Pretty4);

        /** @var ObjectNode $parsed */
        $parsed = $this->parseValue('{"a":1,"b":{"x":1,"y":2}}');
        TestJsonTreeBuilder::format($parsed, TestJsonFormat::Pretty4);

        $this->assertSame($expected, (string) $parsed);
    }

    #[Test]
    public function reformattingAParsedPrettyDocumentThroughAnotherStyleAndBackIsLossless(): void
    {
        $pretty4 = (string) TestJsonTreeBuilder::buildFormatted(['a' => 1, 'b' => ['x' => 1, 'y' => 2]], TestJsonFormat::Pretty4);

        /** @var ObjectNode $parsed */
        $parsed = $this->parseValue($pretty4);
        TestJsonTreeBuilder::format($parsed, TestJsonFormat::Pretty2);
        TestJsonTreeBuilder::format($parsed, TestJsonFormat::Pretty4);

        $this->assertSame($pretty4, (string) $parsed);
    }

    #[Test]
    public function reformattingAParsedPrettyDocumentToMinifiedLeavesNoStaleWhitespace(): void
    {
        /** @var ObjectNode $parsed */
        $parsed = $this->parseValue("{\n    \"a\": 1,\n    \"b\": {\n        \"x\": 1\n    }\n}");

        TestJsonTreeBuilder::format($parsed, TestJsonFormat::Minified);

        $this->assertSame('{"a":1,"b":{"x":1}}', (string) $parsed);
    }

    // -------------------------------------------------------------------------
    // Core-framework fix: '-t*' and '-l*' now resolve to disjoint whitespace
    // sub-kinds instead of both matching "any whitespace, in any form".
    //
    // ObjectNode's grammar is "beginObject -t* ?(-l* member ...)[members] -l*
    // endObject" — two adjacent optional whitespace slots, clearly meant to
    // divide "whitespace trailing the previous token" from "whitespace leading
    // the next one". Before this fix, TagToChoiceCompiler resolved both '-t' and
    // '-l' to the single 'whitespace' region they're both (indiscriminately)
    // tagged on in Whitespace.php — so both slots accepted literally the same
    // thing, and Matcher::matchSequenceNode()'s greedy, non-backtracking `while`
    // loop let the first of the two swallow an entire "\n    " run, leaving the
    // second matching zero. RegionConfigApi::withPossibleNamesForTag() lets a
    // region declare, per tag, a narrower subset of withPossibleNames() — used in
    // Whitespace.php so '-t' resolves only to {trailingWs, inlineWs} (the runtime
    // rename targets that keep the '-t' tag after the per-instance
    // rename/removeTag()) and '-l' only to {emptyLine, leadingWs, inlineWs}. Since
    // a run spanning a newline always tokenizes as a trailingWs region followed by
    // a separate leadingWs one (Whitespace's newline token always closeRegion()s),
    // the outer '-t*' now correctly stops after just the trailingWs part.
    // -------------------------------------------------------------------------

    #[Test]
    public function parsingAPrettyObjectSplitsTrailingAndLeadingWhitespaceAcrossTheRightSlots(): void
    {
        $this->assertGrammarParsing(
            string: "{\n    \"a\": 1\n}",
            grammar: $this->grammar(),
            assertParsingResultValid: function ($result, self $test): void {
                /** @var ObjectNode $object */
                $object = $result->getNodeValue();
                $object->withMembersValidation();

                $test->assertSame(
                    "\n",
                    (string) $object->trivia0,
                    "ObjectNode's own outer trivia0 ('-t*') must hold only the trailingWs part of the run.",
                );
                $test->assertSame(
                    '    ',
                    (string) $object->getMemberUnit(0)[0],
                    "members' own inner leading trivia ('-l*') must hold only the leadingWs (indent) part.",
                );
            },
        );
    }

    /**
     * TestJsonTreeBuilder::format() mirrors that same outer/inner split (see
     * formatOuterTrivia()'s docblock), so a built-and-formatted tree's attribute
     * *shape* — not just its rendered text — should now be indistinguishable from
     * parsing that same text for real.
     */
    #[Test]
    public function formattedTreeHasTheSameAttributeShapeAsARealParseOfItsOwnOutput(): void
    {
        $tree = TestJsonTreeBuilder::buildFormatted(['a' => 1, 'b' => ['x' => 1, 'y' => 2]], TestJsonFormat::Pretty4);
        $text = (string) $tree;

        /** @var ObjectNode $reparsed */
        $reparsed = $this->parseValue($text);

        $this->assertSame((string) $reparsed->trivia0, (string) $tree->trivia0, "outer trivia0 (the '\\n') must match");
        $this->assertSame((string) $reparsed->trivia1, (string) $tree->trivia1, "outer trivia1 must match (empty at depth 0)");

        $builtB = $tree->getMembers()[1]->getNodeValue();
        $reparsedB = $reparsed->getMembers()[1]->getNodeValue();
        $this->assertInstanceOf(ObjectNode::class, $builtB);
        $this->assertInstanceOf(ObjectNode::class, $reparsedB);
        $this->assertSame(
            (string) $reparsedB->trivia1,
            (string) $builtB->trivia1,
            "nested object's outer trivia1 (indent before its own closing brace) must match",
        );
    }

    // -------------------------------------------------------------------------
    // Gotcha: content attributes are inserted verbatim — the builder must escape
    // -------------------------------------------------------------------------

    #[Test]
    public function unescapedStringContentProducesBrokenJson(): void
    {
        // RawRegionAttribute::__toString() is `opener . content . closer` — it does
        // not know it is JSON and will not escape a literal quote in $content for you.
        $member = MemberNode::create('key', PrimitiveNode::create(PrimitiveType::DoubleQuotedString, 'a "quote" inside'));
        $object = ObjectNode::create()->addMember($member);

        $this->assertNull(
            json_decode((string) $object, true),
            'Building a string value without escaping it first must yield invalid JSON — this documents the pitfall, not a bug.',
        );
    }

    #[Test]
    public function jsonTreeBuilderEscapesStringsAndKeysCorrectly(): void
    {
        $data = ['a "tricky" key' => "line1\nline2\ttabbed\\backslash and \x01 control"];

        $tree = TestJsonTreeBuilder::buildFormatted($data, TestJsonFormat::Minified);

        $this->assertSame($data, json_decode((string) $tree, true, flags: JSON_THROW_ON_ERROR));
    }

    // -------------------------------------------------------------------------
    // Modifying an already-parsed tree via the same ParsedTree API
    // -------------------------------------------------------------------------

    /**
     * A parsed ObjectNode/ArrayNode is NOT addMember()/addItem()-ready out of the
     * box: NodeFactory::createNodeFromMatchedSequence() builds it via
     * `new $nodeClass(...)` + NodeAttrFactory, never through the class's own
     * create() factory — so the validityCursor SequenceCarrier::addUnit() requires
     * is never attached, and addUnit() throws LogicException. withMembersValidation()
     * / withItemsValidation() exist on every generated container node precisely to
     * (re)attach it from the attributes that are already there; call it once right
     * after parsing, before any mutation.
     */
    #[Test]
    public function addMemberOnAFreshlyCreatedObjectWorksDirectly(): void
    {
        $object = ObjectNode::create();
        $object->addMember(MemberNode::create('a', PrimitiveNode::create(PrimitiveType::Number, '1')));
        $object->addMember(MemberNode::create('b', PrimitiveNode::create(PrimitiveType::Number, '2')));

        $this->assertSame('{"a":1,"b":2}', (string) $object);
    }

    /**
     * ...but the very same addMember(), after withMembersValidation(), fails on a
     * *parsed* object. A real parse always materializes the trailing
     * `whitespace*[trivia1]` slot (even empty, for minified input — see the
     * `appendMember()`/`appendContentUnit()` docblock in TestJsonTreeBuilder for the
     * attribute dump proving it), which the hand-authored NestedSequence model
     * treats as the end of the (non-repeating) `?(...)[members]` group. Replaying
     * it during withMembersValidation() leaves the cursor "complete", so
     * addUnit('member') is rejected even though the JSON grammar itself allows
     * another member perfectly well. TestJsonTreeBuilder::appendMember() is the
     * origin-agnostic workaround, exercised in the next test.
     */
    #[Test]
    public function addMemberOnAParsedObjectFailsBecauseTheSequenceLooksComplete(): void
    {
        /** @var ObjectNode $object */
        $object = $this->parseValue('{"a":1,"b":2}');
        $object->withMembersValidation();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Sequence is complete, cannot add 'member'.");

        $object->addMember(MemberNode::create('c', PrimitiveNode::create(PrimitiveType::Number, '3')));
    }

    #[Test]
    public function appendMemberViaJsonTreeBuilderWorksOnBothFreshAndParsedObjects(): void
    {
        $fresh = ObjectNode::create();
        $fresh->addMember(MemberNode::create('a', PrimitiveNode::create(PrimitiveType::Number, '1')));
        TestJsonTreeBuilder::appendMember($fresh, MemberNode::create('b', PrimitiveNode::create(PrimitiveType::Number, '2')));
        $this->assertSame('{"a":1,"b":2}', (string) $fresh);

        /** @var ObjectNode $parsed */
        $parsed = $this->parseValue('{"a":1,"b":2}');
        TestJsonTreeBuilder::appendMember($parsed, MemberNode::create('c', PrimitiveNode::create(PrimitiveType::Number, '3')));
        $this->assertSame('{"a":1,"b":2,"c":3}', (string) $parsed);

        // Appending to a *pretty* parsed object keeps the closing brace's own
        // indentation attached to the end, after the newly appended member.
        /** @var ObjectNode $pretty */
        $pretty = $this->parseValue("{\n    \"a\": 1\n}");
        TestJsonTreeBuilder::appendMember($pretty, MemberNode::create('b', PrimitiveNode::create(PrimitiveType::Number, '2')));
        $this->assertSame("{\n    \"a\": 1,\"b\":2\n}", (string) $pretty);
    }

    #[Test]
    public function removingAMemberFromAParsedObjectKeepsRemainingOnesIntact(): void
    {
        /** @var ObjectNode $object */
        $object = $this->parseValue('{"a":1,"b":2,"c":3}');
        $object->withMembersValidation();

        $object->removeMemberByIndex(1);

        $this->assertSame('{"a":1,"c":3}', (string) $object);
        $this->assertSame(['a', 'c'], array_map(
            static fn(MemberNode $m) => $m->getRawIdentifier(),
            $object->getMembers(),
        ));
    }

    #[Test]
    public function replacingAMemberValueInAParsedTreePreservesSurroundingFormatting(): void
    {
        /** @var ObjectNode $object */
        $object = $this->parseValue("{\n    \"a\": 1,\n    \"b\": 2\n}");

        $member = $object->getMembers()[0];
        $member->setNodeValue(PrimitiveNode::create(PrimitiveType::DoubleQuotedString, 'now a string'));

        $this->assertSame("{\n    \"a\": \"now a string\",\n    \"b\": 2\n}", (string) $object);
    }

    #[Test]
    public function editingAPrimitiveInPlaceKeepsItsOwnType(): void
    {
        /** @var ObjectNode $object */
        $object = $this->parseValue('{"a":1}');

        /** @var PrimitiveNode $value */
        $value = $object->getMembers()[0]->getNodeValue();
        $value->setPrimitive(PrimitiveType::Number, '42');

        $this->assertSame('{"a":42}', (string) $object);
    }

    #[Test]
    public function appendItemViaJsonTreeBuilderWorksOnAParsedArray(): void
    {
        /** @var ArrayNode $array */
        $array = $this->parseValue('[1,2]');

        TestJsonTreeBuilder::appendItem($array, PrimitiveNode::create(PrimitiveType::Number, '3'));

        $this->assertSame('[1,2,3]', (string) $array);
    }

    private function parseValue(string $json): ObjectNode|ArrayNode|PrimitiveNode
    {
        $node = null;
        $this->assertGrammarParsing(
            string: $json,
            grammar: $this->grammar(),
            assertParsingResultValid: function ($result) use (&$node): void {
                /** @var \PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Rfc8259\JsonNode $result */
                $node = $result->getNodeValue();
            },
        );

        return $node;
    }

}
