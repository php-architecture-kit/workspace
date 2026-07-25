<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Func\Grammar\Json;

use PhpArchitecture\Parser\Foundation\Parsing\Contract\Placement;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\UnitTriviaPosition;
use PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Json\Json5;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Ver5\ArrayNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Ver5\IdentifierType;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Ver5\JsonNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Ver5\MemberNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Ver5\ObjectNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Ver5\PrimitiveNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Ver5\PrimitiveType;
use PhpArchitecture\Parser\Tests\Func\Grammar\GrammarTestCase;
use PhpArchitecture\Parser\Tests\Func\Grammar\Json\Support\TestJson5TreeBuilder;
use PhpArchitecture\Parser\Tests\Func\Grammar\Json\Support\TestJsonFormat;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use InvalidArgumentException;

/**
 * Json5 analogue of JsonRfc8259TreeBuilderTest (building/formatting/editing
 * purely through the generated ParsedTree classes) plus, unlike that class,
 * comment coverage — Json5 inherits JsonC's `//`/`/* *‍/` support, and the new
 * generic TriviaInsertionPolicy/SequenceUnitTrivia mechanism from
 * [[project-trivia-insertion-policy]] is exercised here directly instead of a
 * hand-written test-support class like TestJsonCComments — Json5CommentPolicy is
 * the only grammar-specific piece needed.
 *
 * Two real bugs found and fixed while building this (see
 * [[project-json5-tree-builder]]):
 *  - Json5.php's own withRootSequence() override (needed for JSON5-only
 *    shape: unquoted/single-quoted identifiers, optional trailing comma)
 *    silently reintroduced the exact narrow-'-l*' own-line-comment parsing
 *    bug JsonC.php had already found and fixed — Json5 extends JsonC, so
 *    parent::grammar() applied that fix, but Json5's own re-declaration of
 *    the same regions overwrote it. Fixed the same way, in Json5.php itself.
 *  - Json5::grammar() never registered its own nodeClassMap overrides —
 *    parsing Json5-only syntax silently produced Json\C\* node instances
 *    (JsonC's, inherited via parent::grammar()) instead of Json5's own
 *    Json\Ver5\* carve-out, even though the origin stamp said otherwise.
 */
#[Group('func')]
final class Json5TreeBuilderTest extends GrammarTestCase
{
    private function grammar()
    {
        return (new Json5())->grammar();
    }

    // -------------------------------------------------------------------------
    // Building from scratch, across all three formats — build() always emits
    // double-quoted keys/strings (the universally-valid subset), so json_encode
    // remains a valid independent oracle even though Json5 allows much more.
    // -------------------------------------------------------------------------

    #[Test]
    public function buildsMinifiedOutputMatchingJsonEncode(): void
    {
        $data = self::sampleData();

        $tree = TestJson5TreeBuilder::buildFormatted($data, TestJsonFormat::Minified);

        $this->assertSame(
            json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            (string) $tree,
        );
    }

    #[Test]
    public function buildsPretty4OutputMatchingJsonEncodePrettyPrint(): void
    {
        $data = self::sampleData();

        $tree = TestJson5TreeBuilder::buildFormatted($data, TestJsonFormat::Pretty4);

        $this->assertSame(
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            (string) $tree,
        );
    }

    #[Test]
    public function builtTreeRoundTripsThroughJsonDecodeForAllFormats(): void
    {
        $data = self::sampleData();

        foreach ([TestJsonFormat::Minified, TestJsonFormat::Pretty2, TestJsonFormat::Pretty4] as $style) {
            $tree = TestJson5TreeBuilder::buildFormatted($data, $style);
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
            'variants' => [
                ['sku' => 'A1', 'inStock' => true],
                ['sku' => 'A2', 'inStock' => false],
            ],
            'empty' => [],
        ];
    }

    // -------------------------------------------------------------------------
    // format() idempotency / re-formatting a parsed tree (mirrors
    // JsonRfc8259TreeBuilderTest's coverage of the same shared shape)
    // -------------------------------------------------------------------------

    #[Test]
    public function formattingTwiceInARowProducesIdenticalOutput(): void
    {
        $tree = TestJson5TreeBuilder::buildFormatted(['a' => 1, 'b' => ['x' => 1, 'y' => 2]], TestJsonFormat::Pretty4);
        $once = (string) $tree;

        TestJson5TreeBuilder::format($tree, TestJsonFormat::Pretty4);

        $this->assertSame($once, (string) $tree);
    }

    #[Test]
    public function reformattingAParsedMinifiedJson5DocumentToPretty4MatchesBuildingItPrettyDirectly(): void
    {
        $expected = (string) TestJson5TreeBuilder::buildFormatted(['a' => 1, 'b' => ['x' => 1, 'y' => 2]], TestJsonFormat::Pretty4);

        // Double-quoted keys, minified — format() only rewrites whitespace, never
        // identifier quoting style, so unquoted/single-quoted input would not match
        // TestJson5TreeBuilder::build()'s always-double-quoted output (see
        // editingAMemberValueInAParsedTreeKeepsTheIdentifiersOwnQuotingStyle()).
        /** @var ObjectNode $parsed */
        $parsed = $this->parseValue('{"a":1,"b":{"x":1,"y":2}}');
        TestJson5TreeBuilder::format($parsed, TestJsonFormat::Pretty4);

        $this->assertSame($expected, (string) $parsed);
    }

    // -------------------------------------------------------------------------
    // Gotcha: content attributes are inserted verbatim — the builder must escape
    // -------------------------------------------------------------------------

    #[Test]
    public function json5TreeBuilderEscapesStringsAndKeysCorrectly(): void
    {
        $data = ['a "tricky" key' => "line1\nline2\ttabbed\\backslash and \x01 control"];

        $tree = TestJson5TreeBuilder::buildFormatted($data, TestJsonFormat::Minified);

        $this->assertSame($data, json_decode((string) $tree, true, flags: JSON_THROW_ON_ERROR));
    }

    // -------------------------------------------------------------------------
    // Modifying an already-parsed tree via the same ParsedTree API
    // -------------------------------------------------------------------------

    #[Test]
    public function addMemberOnAFreshlyCreatedObjectWorksDirectly(): void
    {
        $object = ObjectNode::create();
        $object->addMember(MemberNode::create(IdentifierType::DoubleQuotedString, 'a', PrimitiveNode::create(PrimitiveType::Number, '1')));
        $object->addMember(MemberNode::create(IdentifierType::DoubleQuotedString, 'b', PrimitiveNode::create(PrimitiveType::Number, '2')));

        $this->assertSame('{"a":1,"b":2}', (string) $object);
    }

    /** @see JsonRfc8259TreeBuilderTest::addMemberOnAParsedObjectFailsBecauseTheSequenceLooksComplete() — same underlying SequenceCarrier behavior. */
    #[Test]
    public function addMemberOnAParsedObjectFailsBecauseTheSequenceLooksComplete(): void
    {
        /** @var ObjectNode $object */
        $object = $this->parseValue('{"a":1,"b":2}');
        $object->withMembersValidation();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Sequence is complete, cannot add 'member'.");

        $object->addMember(MemberNode::create(IdentifierType::DoubleQuotedString, 'c', PrimitiveNode::create(PrimitiveType::Number, '3')));
    }

    #[Test]
    public function appendMemberViaJson5TreeBuilderWorksOnBothFreshAndParsedObjects(): void
    {
        $fresh = ObjectNode::create();
        $fresh->addMember(MemberNode::create(IdentifierType::DoubleQuotedString, 'a', PrimitiveNode::create(PrimitiveType::Number, '1')));
        TestJson5TreeBuilder::appendMember($fresh, MemberNode::create(IdentifierType::DoubleQuotedString, 'b', PrimitiveNode::create(PrimitiveType::Number, '2')));
        $this->assertSame('{"a":1,"b":2}', (string) $fresh);

        /** @var ObjectNode $parsed */
        $parsed = $this->parseValue('{a:1,b:2}');
        TestJson5TreeBuilder::appendMember($parsed, MemberNode::create(IdentifierType::NonQuotedIdentifier, 'c', PrimitiveNode::create(PrimitiveType::Number, '3')));
        $this->assertSame('{a:1,b:2,c:3}', (string) $parsed);
    }

    /**
     * Editing a member's *value* in place must not disturb its identifier's
     * own quoting style — an unquoted key parsed as `a:1` must stay unquoted
     * after the value is replaced, not get silently normalized to `"a":1`.
     */
    #[Test]
    public function editingAMemberValueInAParsedTreeKeepsTheIdentifiersOwnQuotingStyle(): void
    {
        /** @var ObjectNode $object */
        $object = $this->parseValue("{unquoted:1,'singleQuoted':2,\"doubleQuoted\":3}");

        foreach ($object->getMembers() as $member) {
            $member->setNodeValue(PrimitiveNode::create(PrimitiveType::Number, '99'));
        }

        $this->assertSame("{unquoted:99,'singleQuoted':99,\"doubleQuoted\":99}", (string) $object);
    }

    // -------------------------------------------------------------------------
    // Real .json5 fixtures (with comments, trailing commas, unquoted/single-
    // quoted keys, hex numbers, Infinity/NaN, ...) must round-trip exactly.
    //
    // identifier_keys.json5 is deliberately excluded: it uses reserved words
    // (`null`/`true`) as unquoted object keys, which real JSON5 allows but this
    // grammar does not yet — `null`/`true`/`false` are tokenized globally as
    // their own dedicated value tokens (higher priority than the generic
    // `nonQuotedIdentifier` pattern), and Json5's `member` rule's identifier
    // choice (`nonQuotedIdentifier|string`) does not list them as alternatives.
    // A separate, pre-existing gap unrelated to this session's comment-focused
    // scope — not attempted here.
    // -------------------------------------------------------------------------

    /** @return array<string,array{string}> */
    public static function realJson5Fixtures(): array
    {
        $testData = dirname(__DIR__, 3) . '/Data/Json/5';
        $generatorSamples = dirname(__DIR__, 6) . '/assets/parser-source-files/json/5';

        return [
            'test data: mixed (with comments)' => [$testData . '/mixed.json5'],
            'test data: numbers' => [$testData . '/numbers.json5'],
            'test data: single-quoted' => [$testData . '/single_quoted.json5'],
            'test data: trailing commas' => [$testData . '/trailing_commas.json5'],
            'generator sample: pretty (heavily commented)' => [$generatorSamples . '/json-5.pretty.json5'],
            'generator sample: minified' => [$generatorSamples . '/json-5.minified.json5'],
            'generator sample: messy' => [$generatorSamples . '/json-5.messy.json5'],
            'generator sample: messy-2' => [$generatorSamples . '/json-5.messy-2.json5'],
            'generator sample: messy-3' => [$generatorSamples . '/json-5.messy-3.json5'],
        ];
    }

    #[Test]
    #[DataProvider('realJson5Fixtures')]
    public function realFixtureFilesRoundTripExactlyThroughTheJson5Grammar(string $path): void
    {
        $source = file_get_contents($path);
        $this->assertNotFalse($source);

        $node = $this->parseValue($source, requireBofEof: false, allowWholeFile: true);

        $this->assertSame($source, (string) $node);
    }

    // -------------------------------------------------------------------------
    // Comments — as complex as possible, via the generic, policy-driven
    // insertInto*()/insertInto*Trivia()/insertInto*TriviaAfterStructural()
    // mechanism (no hand-written comment-placement helper needed this time,
    // unlike TestJsonCComments — see [[project-trivia-insertion-policy]]).
    // -------------------------------------------------------------------------

    #[Test]
    public function insertIntoJsonNodeTrivia0AndTrivia1AddFileHeaderAndFooter(): void
    {
        /** @var JsonNode $root */
        $root = null;
        $this->assertGrammarParsing(
            string: '{a:1}',
            grammar: $this->grammar(),
            assertParsingResultValid: function ($result) use (&$root): void {
                $root = $result;
            },
        );
        $this->assertInstanceOf(JsonNode::class, $root);

        $root->insertIntoTrivia0('generated file', Placement::Before, 0);
        $root->insertIntoTrivia1('end of file');

        // No newline anywhere in this minified document, so both must fall
        // back to block comments — a line comment would either swallow the
        // value or run unterminated to EOF.
        $this->assertSame('/* generated file */{a:1}/* end of file */', (string) $root);

        $reparsed = $this->parseValue((string) $root, requireBofEof: false, allowWholeFile: true);
        $this->assertSame((string) $root, (string) $reparsed);
    }

    #[Test]
    public function insertIntoMemberTrivia0AndTrivia1AddCommentsBetweenKeyAndColonAndBetweenColonAndValue(): void
    {
        /** @var ObjectNode $object */
        $object = $this->parseValue('{a:1}');
        $member = $object->getMembers()[0];

        // MemberNode's own attribute order is identifier, trivia0, colon, trivia1,
        // value — trivia0 sits *between* the key and the colon, not before the key
        // (which belongs to the surrounding ObjectNode::$members unit's own Leading
        // position instead — see insertIntoMembersTriviaLeading...() below).
        $member->insertIntoTrivia0('about a', Placement::Before, 0);
        $member->insertIntoTrivia1('the value');

        $this->assertSame('{a/* about a */:/* the value */1}', (string) $object);

        $reparsed = $this->parseValue((string) $object);
        $this->assertSame((string) $object, (string) $reparsed);
    }

    #[Test]
    public function insertIntoObjectOuterTrivia0AddsACommentRightAfterTheOpeningBrace(): void
    {
        /** @var ObjectNode $object */
        $object = $this->parseValue("{\n    a: 1\n}");

        $object->insertIntoTrivia0('header', Placement::Before, 0);

        $this->assertSame("{// header\n    a: 1\n}", (string) $object);

        $reparsed = $this->parseValue((string) $object);
        $this->assertSame((string) $object, (string) $reparsed);
    }

    /**
     * Own-line comments before the first, a middle, and the last member of the
     * same object — exercises SequenceUnitTrivia::locate()'s Leading position
     * across every distinct structural shape a unit can have (see
     * [[project-trivia-insertion-policy]]'s "Design flaw caught and fixed"
     * section for why this can't be done by trivia-group *name* alone).
     */
    #[Test]
    public function insertIntoMembersTriviaLeadingAddsOwnLineCommentsAtFirstMiddleAndLastUnit(): void
    {
        /** @var ObjectNode $object */
        $object = $this->parseValue("{\n    a: 1,\n    b: 2,\n    c: 3\n}");
        $object->withMembersValidation();

        $object->insertIntoMembersTrivia(0, UnitTriviaPosition::Leading, 'about a', Placement::Before, 0);
        $object->insertIntoMembersTrivia(1, UnitTriviaPosition::Leading, 'about b', Placement::Before, 0);
        $object->insertIntoMembersTrivia(2, UnitTriviaPosition::Leading, 'about c', Placement::Before, 0);

        $expected = <<<JSON5
        {
        /* about a */    a: 1,
        /* about b */    b: 2,
        /* about c */    c: 3
        }
        JSON5;
        $this->assertSame($expected, (string) $object);

        $reparsed = $this->parseValue((string) $object);
        $this->assertSame((string) $object, (string) $reparsed);
    }

    /**
     * Default placement appends at the group's *tail* — after its existing
     * "\n" — so nothing follows the insertion point and the policy correctly
     * falls back to a block comment (a line comment there would run into
     * "active: true" unterminated). Landing the comment inline on the comma's
     * own line, TestJsonCComments-style, needs an explicit leading InlineWsNode
     * the caller supplies — this raw primitive only guarantees a *safe*
     * position and comment type, not that cosmetic layer; see
     * [[project-trivia-insertion-policy]].
     */
    #[Test]
    public function insertIntoMembersTriviaAfterStructuralCommaAddsACommentAfterTheMembersOwnComma(): void
    {
        /** @var ObjectNode $object */
        $object = $this->parseValue("{\n    age: 30,\n    active: true\n}");
        $object->withMembersValidation();

        $object->insertIntoMembersTriviaAfterStructural(0, 'comma', 'wiek');

        $this->assertSame("{\n    age: 30,\n/* wiek */    active: true\n}", (string) $object);

        $reparsed = $this->parseValue((string) $object);
        $this->assertSame((string) $object, (string) $reparsed);
    }

    /**
     * Json5-only: an *optional trailing comma* is its own, distinctly-named
     * structural attribute ('trailingComma', separate from the loop's own
     * 'comma') — a real, concrete case where a unit legitimately has *two*
     * different named structural tokens, not "the" separator. This is exactly
     * the shape the old (fixed) AfterSeparator heuristic — "exactly one
     * StructureAttribute slot" — would have refused to handle at all; see
     * [[project-trivia-insertion-policy]]'s "Design flaw caught and fixed".
     */
    #[Test]
    public function insertIntoMembersTriviaAfterStructuralTrailingCommaAddsACommentAfterTheFinalTrailingComma(): void
    {
        /** @var ObjectNode $object */
        $object = $this->parseValue("{\n    a: 1,\n    b: 2,\n}");
        $object->withMembersValidation();

        $object->insertIntoMembersTriviaAfterStructural(1, 'trailingComma', 'trailing note');

        $this->assertSame("{\n    a: 1,\n    b: 2,\n/* trailing note */}", (string) $object);

        $reparsed = $this->parseValue((string) $object);
        $this->assertSame((string) $object, (string) $reparsed);
    }

    #[Test]
    public function insertIntoItemsTriviaAndInsertIntoItemsTriviaAfterStructuralWorkTheSameWayForArrays(): void
    {
        /** @var ArrayNode $array */
        $array = $this->parseValue("[\n    'php',\n    'json5',\n    'parser',\n]");
        $array->withItemsValidation();

        $array->insertIntoItemsTrivia(0, UnitTriviaPosition::Leading, 'first', Placement::Before, 0);
        $array->insertIntoItemsTriviaAfterStructural(1, 'comma', 'second');
        $array->insertIntoItemsTriviaAfterStructural(2, 'trailingComma', 'trailing');

        $expected = <<<JSON5
        [
        /* first */    'php',
            'json5',
        /* second */    'parser',
        /* trailing */]
        JSON5;
        $this->assertSame($expected, (string) $array);

        $reparsed = $this->parseValue((string) $array);
        $this->assertSame((string) $array, (string) $reparsed);
    }

    /**
     * The flagship scenario: build a plain data structure via TestJson5TreeBuilder
     * (no comments), then transform it into a heavily-commented Json5 document
     * by inserting different comment kinds at several different structural
     * positions (file header, member leading, after-comma, after-trailing-
     * comma, nested object), then confirm the result is itself valid,
     * re-parseable Json5.
     */
    #[Test]
    public function transformingABuiltJson5DocumentByAddingCommentsAtSeveralPositionsProducesValidReparsableJson5(): void
    {
        $plain = TestJson5TreeBuilder::buildFormatted(
            ['name' => 'Alice', 'age' => 30, 'address' => ['city' => 'Warsaw']],
            TestJsonFormat::Pretty4,
        );

        /** @var JsonNode $root */
        $root = null;
        $this->assertGrammarParsing(
            string: (string) $plain,
            grammar: $this->grammar(),
            assertParsingResultValid: function ($result) use (&$root): void {
                $root = $result;
            },
        );

        /** @var ObjectNode $object */
        $object = $root->getNodeValue();
        $object->withMembersValidation();

        $root->insertIntoTrivia0('generated fixture', Placement::Before, 0);
        $object->insertIntoMembersTrivia(0, UnitTriviaPosition::Leading, 'name field', Placement::Before, 0);
        $object->insertIntoMembersTriviaAfterStructural(1, 'comma', 'years');

        /** @var ObjectNode $address */
        $address = $object->getMembers()[2]->getNodeValue();
        $this->assertInstanceOf(ObjectNode::class, $address);
        $address->insertIntoTrivia0('nested', Placement::Before, 0);

        // The policy earns its keep at every one of these positions: block for
        // the file header (JsonNode's own trivia0 is genuinely empty on a
        // freshly re-parsed, no-leading-whitespace document — no line break to
        // rely on); block again after "age"'s comma (default placement lands
        // after the existing "\n", nothing guaranteed after that point either);
        // but a real line comment for "nested" — address's own trivia0 already
        // holds the "\n" TestJson5TreeBuilder::format() put there, so inserting
        // *before* it (offset 0) still has that "\n" ahead of the insertion.
        $expected = <<<JSON5
        /* generated fixture */{
        /* name field */    "name": "Alice",
            "age": 30,
        /* years */    "address": {// nested
                "city": "Warsaw"
            }
        }
        JSON5;

        $this->assertSame($expected, (string) $root);

        $reparsed = $this->parseValue((string) $root, requireBofEof: false, allowWholeFile: true);
        $this->assertSame((string) $root, (string) $reparsed);
    }

    // -------------------------------------------------------------------------
    // Parse a genuinely messy .json5 file (irregular indentation, inconsistent
    // spacing, everything crammed together in places), pretty-print it purely
    // through the ParsedTree classes, and confirm the result is both exactly
    // right and independently reparseable.
    // -------------------------------------------------------------------------

    #[Test]
    public function parsingAMessyJson5FileAndPrettyFormattingItMatchesBuildingTheSameDataPrettyDirectly(): void
    {
        $path = dirname(__DIR__, 3) . '/Data/Json/5/messy_no_comments.json5';
        $contents = file_get_contents($path);
        $this->assertNotFalse($contents);
        $source = trim($contents);

        // Independent oracle: json_decode() understands this fixture's content
        // (deliberately restricted to double-quoted keys/strings, no trailing
        // commas, no comments — the messiness here is purely irregular
        // whitespace, same spirit as JsonRfc8259TreeBuilderTest's messiness
        // coverage) — so re-*building* the decoded data pretty gives a
        // ground truth independent of the formatter under test.
        $data = json_decode($source, true, flags: JSON_THROW_ON_ERROR);
        $expected = (string) TestJson5TreeBuilder::buildFormatted($data, TestJsonFormat::Pretty4);

        /** @var ObjectNode $parsed */
        $parsed = $this->parseValue($source);
        TestJson5TreeBuilder::format($parsed, TestJsonFormat::Pretty4);

        $this->assertSame($expected, (string) $parsed);

        $reparsed = $this->parseValue((string) $parsed);
        $this->assertSame((string) $parsed, (string) $reparsed);
    }

    #[Test]
    public function parsingAMessyJson5FileAndPrettyFormattingItAlsoWorksForMinifiedAndTwoSpaceStyles(): void
    {
        $path = dirname(__DIR__, 3) . '/Data/Json/5/messy_no_comments.json5';
        $contents = file_get_contents($path);
        $this->assertNotFalse($contents);
        $source = trim($contents);
        $data = json_decode($source, true, flags: JSON_THROW_ON_ERROR);

        foreach ([TestJsonFormat::Minified, TestJsonFormat::Pretty2, TestJsonFormat::Pretty4] as $style) {
            $expected = (string) TestJson5TreeBuilder::buildFormatted($data, $style);

            /** @var ObjectNode $parsed */
            $parsed = $this->parseValue($source);
            TestJson5TreeBuilder::format($parsed, $style);

            $this->assertSame($expected, (string) $parsed, "Failed for style {$style->name}");
        }
    }

    /**
     * format() clears every whitespace-carrying trivia group it touches (see
     * TestJson5TreeBuilder::formatSequence()'s docblock) — it has no concept of
     * comments at all, unlike the policy-driven insertInto*() family exercised
     * above. A "messy" real-world .json5 file very often *has* comments (see
     * the heavily-commented fixtures in realJson5Fixtures()), so this pins the
     * actual, current behavior explicitly rather than leaving it an unstated
     * surprise: pretty-formatting a commented document silently drops the
     * comments, it does not error or corrupt the file. Preserving comments
     * through a reformat (re-indenting around them) is a materially bigger
     * feature — not attempted here; see [[project-json5-tree-builder]].
     */
    #[Test]
    public function prettyFormattingAJson5DocumentThatHasCommentsSilentlyDropsThem(): void
    {
        /** @var ObjectNode $parsed */
        $parsed = $this->parseValue("{\n    // note about a\n    a: 1,\n    b: /* inline */ 2,\n}");

        TestJson5TreeBuilder::format($parsed, TestJsonFormat::Pretty4);

        // format() never touches identifier quoting either — unquoted keys
        // stay unquoted (see editingAMemberValueInAParsedTreeKeepsTheIdentifiersOwnQuotingStyle()).
        // The trailing comma in the source is likewise untouched — Json5's own
        // 'trailingComma' structural attribute falls into formatSequence()'s
        // catch-all branch, kept as-is (not reformatted, not dropped).
        $this->assertSame("{\n    a: 1,\n    b: 2,\n}", (string) $parsed);
        $this->assertStringNotContainsString('note about a', (string) $parsed);
        $this->assertStringNotContainsString('inline', (string) $parsed);
    }

    private function parseValue(string $json, bool $requireBofEof = true, bool $allowWholeFile = false): ObjectNode|ArrayNode|PrimitiveNode|JsonNode
    {
        $node = null;
        $this->assertGrammarParsing(
            string: $json,
            grammar: $this->grammar(),
            assertParsingResultValid: function ($result) use (&$node, $allowWholeFile): void {
                /** @var JsonNode $result */
                $node = $allowWholeFile ? $result : $result->getNodeValue();
            },
            requireBofEof: $requireBofEof,
        );

        $this->assertNotNull($node);

        return $node;
    }
}
