<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Func\Grammar\Json;

use PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Json\JsonC;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\C\ArrayNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\C\JsonNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\C\LineCommentNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\C\ObjectNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\C\PrimitiveNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Comment\SingleLineNode;
use PhpArchitecture\Parser\Tests\Func\Grammar\GrammarTestCase;
use PhpArchitecture\Parser\Tests\Func\Grammar\Json\Support\TestJsonCComments;
use PhpArchitecture\Parser\Tests\Func\Grammar\Json\Support\TestJsonFormat;
use PhpArchitecture\Parser\Tests\Func\Grammar\Json\Support\TestJsonTreeBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use LogicException;

/**
 * Explores what it takes to transform a plain JSON document into JSONC by
 * *adding* comments — as opposed to JsonRfc8259TreeBuilderTest, which explores
 * building/formatting plain JSON. Two kinds of bugs surfaced, both fixed this
 * session and documented in [[project-jsonc-comment-insertion]]:
 *
 *  - A real grammar bug: JsonC could not parse a comment sitting on its own
 *    line at all (`{\n    // c\n    "a":1}`) — fixed in JsonC.php (see its
 *    docblock). ownLineCommentBeforeFirstMemberNowParsesCorrectly() pins it.
 *  - A Tree Generator bug: `OptionalRawAttribute` (the type behind a comment's
 *    own optional leadingWs/trailingWs) had no case at all in the generator's
 *    attribute-kind dispatch, so LineCommentNode::create()/SingleLineNode::create()
 *    silently built a shorter attribute list than their own property hooks
 *    expected, breaking getRawContent()/setRawContent() on any freshly-built
 *    comment. Fixed in FacadeClassRenderer/NodeSchemaCollector/AttributeSchema
 *    (Infrastructure/TreeSchema/Generator) and classes regenerated via
 *    `parser:tree:generate` — createdLineCommentNowHasAWorkingContentAccessor()
 *    and its SingleLineNode analogue pin the fix. `SingleLineNode::create()`
 *    still can't produce a plain (asterisk-less) `/* ... *‍/` — a separate,
 *    still-open StructureAttribute gap — so TestJsonCComments::block() still
 *    builds that shape by hand; see its docblock.
 */
#[Group('func')]
final class JsonCTreeBuilderTest extends GrammarTestCase
{
    private function grammar()
    {
        return (new JsonC())->grammar();
    }

    // -------------------------------------------------------------------------
    // Real .jsonc fixtures must round-trip exactly through the JsonC grammar
    // -------------------------------------------------------------------------

    /** @return array<string,array{string}> */
    public static function realJsoncFixtures(): array
    {
        $dir = dirname(__DIR__, 3) . '/Data/Json/c';
        return [
            'line comments' => [$dir . '/line_comments.jsonc'],
            'block comments' => [$dir . '/block_comments.jsonc'],
            'mixed' => [$dir . '/mixed.jsonc'],
        ];
    }

    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('realJsoncFixtures')]
    public function realFixtureFilesRoundTripExactlyThroughTheJsonCGrammar(string $path): void
    {
        $source = file_get_contents($path);
        $this->assertNotFalse($source);

        $node = $this->parseValue($source, requireBofEof: false, allowWholeFile: true);

        $this->assertSame($source, (string) $node);
    }

    // -------------------------------------------------------------------------
    // Core-framework fix: an own-line comment (indent, comment, newline, indent,
    // content) previously could not be parsed at all.
    //
    // JsonRfc8259's root sequence is "beginObject -t* ?(-l* member ...)[members]
    // -l* endObject" — '-l*' was narrowed (by Whitespace's withPossibleNamesForTag(),
    // see [[project-whitespace-tag-resolution-fix]]) to exclude trailingWs, so
    // plain whitespace runs split correctly across -t*/-l*. But a comment on its
    // own line *always* ends in its own trailingWs (the newline that closes it)
    // before the next leadingWs (indent) begins — e.g. leadingWs+lineComment+
    // trailingWs+leadingWs — and no single -l* slot can swallow that internal
    // trailingWs, nor is there a second -t* slot positioned to catch it. Matcher
    // has no backtracking, so the whole `?(...)[members]` sequence failed to
    // match and parsing threw "Root sequence 'object' could not be matched".
    // Fixed by widening JsonC's own '-l*' slots (only those — '-t*' needed no
    // change, since a comment trailing existing content always stays within
    // '-t*''s own territory) to the fully undiscriminated bare '-*', local to
    // JsonC's 'object'/'array' regions only (fresh Region instances per
    // grammar() call — JsonRfc8259 and every other Whitespace-derived grammar
    // keep the strict split, unaffected).
    // -------------------------------------------------------------------------

    #[Test]
    public function ownLineCommentBeforeFirstMemberNowParsesCorrectly(): void
    {
        $source = "{\n    // note about a\n    \"a\": 1\n}";

        /** @var ObjectNode $object */
        $object = $this->parseValue($source);
        $object->withMembersValidation();

        $this->assertSame($source, (string) $object);

        $leading = $object->getMemberUnit(0)[0];
        $this->assertInstanceOf(\PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\GroupAttribute::class, $leading);
        $comment = null;
        foreach ($leading->getNodes() as $node) {
            if ($node instanceof LineCommentNode) {
                $comment = $node;
            }
        }
        $this->assertNotNull($comment, 'The own-line comment must land inside the leading trivia group of the first member.');
        $this->assertSame('note about a', $comment->content->content);
    }

    #[Test]
    public function ownLineCommentBeforeASubsequentMemberAndBeforeTheClosingBraceBothParseCorrectly(): void
    {
        $source = "{\n    \"a\": 1,\n    // note about b\n    \"b\": 2\n    // trailing note\n}";

        /** @var ObjectNode $object */
        $object = $this->parseValue($source);

        $this->assertSame($source, (string) $object);
    }

    // -------------------------------------------------------------------------
    // Tree Generator fix (this session): OptionalRawAttribute (the type behind
    // a comment's own optional leadingWs/trailingWs) previously had no case at
    // all in FacadeClassRenderer/NodeSchemaCollector/AttributeSchema's
    // attribute-kind dispatch, so create() silently omitted such slots — see
    // this class's docblock and [[project-jsonc-comment-insertion]].
    // -------------------------------------------------------------------------

    #[Test]
    public function createdLineCommentNowHasAWorkingContentAccessor(): void
    {
        $node = LineCommentNode::create(leadingWs: ' ', content: 'hello');

        $this->assertSame('// hello', (string) $node);
        $this->assertSame('hello', $node->getRawContent());
        $this->assertSame(' ', $node->getRawLeadingWs());
        $this->assertNull($node->getRawTrailingWs(), 'trailingWs was not given, so it must be absent, not an empty string.');

        // The optional slots really are optional: omitting leadingWs is valid.
        $bare = LineCommentNode::create(leadingWs: null, content: 'hello');
        $this->assertSame('//hello', (string) $bare);
        $this->assertNull($bare->getRawLeadingWs());
    }

    #[Test]
    public function createdSingleLineBlockCommentNowHasAWorkingContentAccessorButStillForcesJavaDocStyle(): void
    {
        $node = SingleLineNode::create(leadingWs: ' ', content: 'hello', trailingWs: ' ');

        $this->assertSame('hello', $node->getRawContent());
        $this->assertSame(' ', $node->getRawLeadingWs());
        $this->assertSame(' ', $node->getRawTrailingWs());

        // Both asterisks are still hardcoded present — a separate, still-open
        // gap (StructureAttribute has no notion of "optionally present" in
        // create() at all), unrelated to the OptionalRawAttribute fix above.
        $this->assertSame('/** hello **/', (string) $node);
    }

    #[Test]
    public function jsonCCommentsLineBuildsAWorkingCommentNodeWithAFunctioningAccessor(): void
    {
        $node = TestJsonCComments::line('hello world');

        $this->assertSame('// hello world', (string) $node);
        $this->assertSame('hello world', $node->getRawContent());
    }

    #[Test]
    public function jsonCCommentsBlockBuildsAPlainAsteriskLessCommentNode(): void
    {
        $node = TestJsonCComments::block('hello world');

        $this->assertSame('/* hello world */', (string) $node);
    }

    // -------------------------------------------------------------------------
    // Inserting comments at various positions into an already-parsed tree, with
    // indentation derived from the surrounding tree rather than hand-typed by
    // the caller (TestJsonCComments::prependOwnLineComment() takes no indent
    // parameter at all — it reads the sibling's existing LeadingWsNode).
    // -------------------------------------------------------------------------

    #[Test]
    public function prependOwnLineCommentInsertsBeforeFirstMemberWithDerivedIndent(): void
    {
        /** @var ObjectNode $object */
        $object = $this->parseValue("{\n    \"a\": 1,\n    \"b\": 2\n}");

        TestJsonCComments::prependOwnLineComment($object, 0, TestJsonCComments::line('leading note'));

        $this->assertSame(
            "{\n    // leading note\n    \"a\": 1,\n    \"b\": 2\n}",
            (string) $object,
        );
    }

    #[Test]
    public function prependOwnLineCommentBeforeASubsequentMemberMatchesSiblingIndent(): void
    {
        /** @var ObjectNode $object */
        $object = $this->parseValue("{\n    \"a\": 1,\n    \"b\": 2\n}");

        TestJsonCComments::prependOwnLineComment($object, 1, TestJsonCComments::block('about b'));

        $this->assertSame(
            "{\n    \"a\": 1,\n    /* about b */\n    \"b\": 2\n}",
            (string) $object,
        );
    }

    /**
     * The strongest version of "indentation is derived, not hand-written": the
     * expected indent string here is itself read off the tree (never typed as a
     * literal in this test), and the same insertion call is exercised at two
     * different indent widths and one extra nesting level — proving the derived
     * indent tracks whatever is actually there rather than a baked-in assumption.
     */
    #[Test]
    public function ownLineCommentIndentAutomaticallyTracksSurroundingStyleAndDepth(): void
    {
        foreach ([TestJsonFormat::Pretty2, TestJsonFormat::Pretty4] as $style) {
            $plain = TestJsonTreeBuilder::buildFormatted(['a' => 1, 'nested' => ['b' => 2, 'c' => 3]], $style);

            /** @var ObjectNode $object */
            $object = $this->parseValue((string) $plain);
            $object->withMembersValidation();

            /** @var ObjectNode $nested */
            $nested = $object->getMembers()[1]->getNodeValue();
            $nested->withMembersValidation();

            // Read the indent that's already there (before "c") instead of
            // typing it — this is the oracle the inserted comment must match.
            $siblingIndent = (string) $nested->getMemberUnit(1)[3];
            $this->assertNotSame('', $siblingIndent, "Sanity: {$style->name} must actually indent nested members.");

            TestJsonCComments::prependOwnLineComment($nested, 1, TestJsonCComments::line('about c'));

            $this->assertStringContainsString(
                "{$siblingIndent}// about c\n{$siblingIndent}\"c\"",
                (string) $nested,
                "Comment indent must match the sibling's own indent for style {$style->name}.",
            );
        }
    }

    #[Test]
    public function appendTrailingCommentAttachesRightAfterTheValueBeforeItsComma(): void
    {
        /** @var ObjectNode $object */
        $object = $this->parseValue('{"a":1,"b":2}');

        TestJsonCComments::appendTrailingComment($object, 0, TestJsonCComments::block('a note'));

        $this->assertSame('{"a":1 /* a note */,"b":2}', (string) $object);
    }

    #[Test]
    public function appendAfterCommaAttachesOnTheSameLineBeforeTheNewline(): void
    {
        /** @var ObjectNode $object */
        $object = $this->parseValue("{\n    \"age\": 30,\n    \"active\": true\n}");

        TestJsonCComments::appendAfterComma($object, 0, TestJsonCComments::line('wiek'));

        $this->assertSame(
            "{\n    \"age\": 30, // wiek\n    \"active\": true\n}",
            (string) $object,
        );
    }

    #[Test]
    public function appendAfterCommaThrowsWhenTheTargetMemberHasNoTrailingComma(): void
    {
        /** @var ObjectNode $object */
        $object = $this->parseValue('{"a":1,"b":2}');

        $this->expectException(LogicException::class);
        TestJsonCComments::appendAfterComma($object, 1, TestJsonCComments::line('x'));
    }

    #[Test]
    public function prependAndAppendFileCommentAddHeaderAndFooterAroundTheRootValue(): void
    {
        /** @var JsonNode $root */
        $root = null;
        $this->assertGrammarParsing(
            string: '{"a":1}',
            grammar: $this->grammar(),
            assertParsingResultValid: function ($result) use (&$root): void {
                $root = $result;
            },
        );

        TestJsonCComments::prependFileComment($root, TestJsonCComments::line(' file header'));
        TestJsonCComments::appendFileComment($root, TestJsonCComments::line(' file footer'));

        $this->assertSame("//  file header\n{\"a\":1}\n//  file footer", (string) $root);
    }

    #[Test]
    public function insertingCommentsIntoAnArrayWorksTheSameWayAsIntoAnObject(): void
    {
        /** @var ArrayNode $array */
        $array = $this->parseValue("[\n    \"php\",\n    \"json\",\n    \"parser\"\n]");

        // A block comment, not a line comment: "php" is not the last item, so
        // a comma still has to follow on the same line (see the next test).
        TestJsonCComments::appendTrailingComment($array, 0, TestJsonCComments::block('language'));
        TestJsonCComments::prependOwnLineComment($array, 2, TestJsonCComments::line('the role'));

        $this->assertSame(
            "[\n    \"php\" /* language */,\n    \"json\",\n    // the role\n    \"parser\"\n]",
            (string) $array,
        );
    }

    /**
     * A '//' comment consumes everything to the end of its line, including
     * anything meant to still be there structurally — appendTrailingComment()
     * guards against this rather than silently producing a corrupt document
     * (a comma — or here, the closing bracket — swallowed into a comment is
     * easy to miss just by reading the output, since the text still *looks*
     * plausible). This is not just a "not the last item" check: even the last
     * item is unsafe in a *minified* document, since there is no newline
     * before the closing bracket either.
     */
    #[Test]
    public function appendTrailingLineCommentBeforeAFollowingCommaIsRejectedInsteadOfSwallowingIt(): void
    {
        /** @var ArrayNode $array */
        $array = $this->parseValue('["php","json"]');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("existing line break");
        TestJsonCComments::appendTrailingComment($array, 0, TestJsonCComments::line('language'));
    }

    #[Test]
    public function appendTrailingLineCommentOnTheLastItemOfAMinifiedArrayIsAlsoRejected(): void
    {
        /** @var ArrayNode $array */
        $array = $this->parseValue('["php","json"]');

        $this->expectException(LogicException::class);
        TestJsonCComments::appendTrailingComment($array, 1, TestJsonCComments::line('format'));
    }

    /**
     * ...but once there's already a newline for the comment to land in front
     * of — the ordinary pretty-printed case — a line comment is safe even on
     * the last item, since the closing bracket is on its own line already.
     */
    #[Test]
    public function appendTrailingLineCommentOnTheLastItemOfAPrettyArrayIsAllowed(): void
    {
        /** @var ArrayNode $array */
        $array = $this->parseValue("[\n    \"php\",\n    \"json\"\n]");

        TestJsonCComments::appendTrailingComment($array, 1, TestJsonCComments::line('format'));

        $this->assertSame("[\n    \"php\",\n    \"json\" // format\n]", (string) $array);
    }

    // -------------------------------------------------------------------------
    // The flagship scenario: transform a *plain* JSON document (no comments)
    // into JSONC by inserting several different comments at several different
    // positions, then confirm the result both matches expectations and is
    // itself valid JsonC input (round-trips through the same grammar again).
    // -------------------------------------------------------------------------

    #[Test]
    public function transformingAPlainJsonDocumentIntoJsonCByAddingCommentsAtSeveralPositionsProducesValidReparsableJsonC(): void
    {
        $plain = TestJsonTreeBuilder::buildFormatted(
            ['name' => 'Alice', 'age' => 30, 'active' => true],
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

        TestJsonCComments::prependFileComment($root, TestJsonCComments::line(' generated fixture'));
        TestJsonCComments::prependOwnLineComment($object, 0, TestJsonCComments::line('name field'));
        TestJsonCComments::appendAfterComma($object, 1, TestJsonCComments::line('years'));
        TestJsonCComments::appendTrailingComment($object, 2, TestJsonCComments::block('flag'));

        $expected = <<<JSONC
        //  generated fixture
        {
            // name field
            "name": "Alice",
            "age": 30, // years
            "active": true /* flag */
        }
        JSONC;

        $this->assertSame($expected, (string) $root);

        // And the transformed document must itself be valid, re-parseable JsonC.
        $reparsed = $this->parseValue((string) $root, requireBofEof: false, allowWholeFile: true);
        $this->assertSame((string) $root, (string) $reparsed);
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
