<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Func\Grammar\Json;

use PhpArchitecture\Parser\Foundation\Parsing\Contract\Placement;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\TriviaPolicyRegistry;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\UnitTriviaPosition;
use PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Json\JsonC;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\C\ArrayNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\C\BlockCommentNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\C\JsonNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\C\LineCommentNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\C\MemberNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\C\ObjectNode;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\GroupAttribute;
use PhpArchitecture\Parser\Tests\Func\Grammar\GrammarTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use LogicException;

/**
 * Covers the generated, policy-driven `insertInto{Prop}()` mechanism (Tree
 * Generator: FacadeClassRenderer::renderTriviaInsertionMethod(), backed by
 * TriviaInsertionPolicy/TriviaInsertionContext/TriviaPolicyRegistry) — the
 * generic hook the earlier hand-rolled TestJsonCComments test helper motivated.
 * See [[project-trivia-insertion-policy]].
 *
 * Two halves:
 *  - The four *outer* GroupAttribute properties (ObjectNode/ArrayNode's own
 *    trivia0/trivia1, MemberNode's trivia0/trivia1, JsonNode's trivia0/trivia1)
 *    whose observed shape includes a non-whitespace alternative, reached via
 *    `insertInto{Prop}()`.
 *  - The SequenceAttribute-*internal* trivia0/1/2 slots inside `members`/
 *    `items` (previously reachable only via TestJsonCComments's hand-written
 *    getMemberUnit() plumbing) — now reached two ways, matching what
 *    SequenceAttribute itself actually distinguishes (content vs. named
 *    structural, nothing more — no built-in "separator" concept):
 *      - `insertInto{Prop}Trivia(int $unitIndex, UnitTriviaPosition $position, ...)`
 *        for the two positions any unit always has (`Leading`/`Trailing`).
 *      - `insertInto{Prop}TriviaAfterStructural(int $unitIndex, string $structuralName, ...)`
 *        for "right after this *named* structural token" (e.g. 'comma') —
 *        the name is a plain runtime argument, not an inferred enum case.
 *    Both backed by the framework-generic SequenceUnitTrivia (see
 *    unitTrivia*() tests below and [[project-trivia-insertion-policy]] for
 *    why bare group *names* inside one unit are ambiguous, and why
 *    "separator" isn't a real SequenceAttribute concept).
 */
#[Group('func')]
final class TriviaInsertionPolicyTest extends GrammarTestCase
{
    private function grammar()
    {
        return (new JsonC())->grammar();
    }

    #[Test]
    public function unregisteredFacadeClassThrowsAClearError(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('No TriviaInsertionPolicy registered');
        TriviaPolicyRegistry::resolve('SomeClassNoOneEverRegistered');
    }

    #[Test]
    public function insertingWhereALineBreakIsAlreadyGuaranteedBuildsALineComment(): void
    {
        // (new JsonC())->grammar() must run at least once to populate the
        // registry — parseValue() below does exactly that.
        /** @var ObjectNode $object */
        $object = $this->parseValue("{\n    \"a\": 1\n}");

        // Object's own outer trivia0 (right after '{') already holds "\n" —
        // inserting *before* it (offset 0) still has that "\n" ahead of it.
        $object->insertIntoTrivia0('header', Placement::Before, 0);

        $comment = $object->trivia0->getNodes()[0];
        $this->assertInstanceOf(LineCommentNode::class, $comment);
        $this->assertSame("{// header\n    \"a\": 1\n}", (string) $object);
    }

    /**
     * Regression test: the first cut of this mechanism checked "does the
     * whole group contain a newline anywhere", not "is there one *after*
     * where the node will actually land". Appending (the default Placement)
     * to a group that has "\n" *earlier* but nothing after it passed that
     * check incorrectly, and a resulting '//' comment silently swallowed
     * `"a": 1,` entirely — a real, caught-before-shipping bug in this
     * mechanism's own safety check. Fixed by making TriviaInsertionContext
     * resolve the same array index GroupAttribute::addNode() would use and
     * only checking from there onward.
     */
    #[Test]
    public function appendingToAGroupWithAnEarlierButNotTrailingNewlineStillChoosesABlockComment(): void
    {
        /** @var ObjectNode $object */
        $object = $this->parseValue("{\n    \"a\": 1,\n    \"b\": 2\n}");

        // Default placement: append at the *end* of trivia0 (after its
        // existing "\n", with nothing guaranteeing a break after that).
        $object->insertIntoTrivia0('header');

        $comment = $object->trivia0->getNodes()[array_key_last($object->trivia0->getNodes())];
        $this->assertInstanceOf(
            BlockCommentNode::class,
            $comment,
            'A line comment here would swallow the following "a": 1 entirely.',
        );

        $text = (string) $object;
        $this->assertStringContainsString('"a": 1', $text, 'The member must survive, not be swallowed into a comment.');

        // And the result must actually be valid, re-parseable JsonC.
        $reparsed = $this->parseValue($text);
        $this->assertSame($text, (string) $reparsed);
    }

    #[Test]
    public function insertingIntoAnEmptyGroupChoosesABlockComment(): void
    {
        /** @var ObjectNode $object */
        $object = $this->parseValue('{"a":1}');

        // Minified: object's outer trivia1 (before '}') is empty — no
        // newline anywhere, so a line comment would run into '}' itself.
        $object->insertIntoTrivia1('footer');

        $comment = $object->trivia1->getNodes()[0];
        $this->assertInstanceOf(BlockCommentNode::class, $comment);

        $text = (string) $object;
        $reparsed = $this->parseValue($text);
        $this->assertSame($text, (string) $reparsed);
    }

    #[Test]
    public function memberNodeInsertIntoTrivia1BuildsAnInlineCommentBetweenColonAndValue(): void
    {
        /** @var ObjectNode $object */
        $object = $this->parseValue('{"a":1}');
        $member = $object->getMembers()[0];
        $this->assertInstanceOf(MemberNode::class, $member);

        $member->insertIntoTrivia1('about a');

        $this->assertSame('{"a":/* about a */1}', (string) $object);
    }

    #[Test]
    public function jsonNodeInsertIntoTrivia0AndTrivia1AddHeaderAndFooterComments(): void
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
        $this->assertInstanceOf(JsonNode::class, $root);

        $root->insertIntoTrivia0('header', Placement::Before, 0);
        $root->insertIntoTrivia1('footer');

        // Neither JsonNode's own trivia0 nor trivia1 has any newline in a
        // minified single-line document, so both must fall back to block
        // comments (a line comment would swallow the value or run unterminated).
        $this->assertSame('/* header */{"a":1}/* footer */', (string) $root);
    }

    #[Test]
    public function insertingIntoAnArrayItemsOuterTrivia0BehavesTheSameWayAsAnObject(): void
    {
        /** @var ArrayNode $array */
        $array = $this->parseValue("[\n    1,\n    2\n]");

        $array->insertIntoTrivia0('header', Placement::Before, 0);

        $comment = $array->trivia0->getNodes()[0];
        $this->assertInstanceOf(LineCommentNode::class, $comment, 'Already has "\n" ahead of the insertion point.');
        $this->assertSame("[// header\n    1,\n    2\n]", (string) $array);
    }

    // -------------------------------------------------------------------------
    // insertInto{Prop}Trivia() — the SequenceAttribute-internal mechanism
    // (SequenceUnitTrivia::locate() + the same TriviaInsertionPolicy/Context/
    // Registry as above, just reached via a unit index + UnitTriviaPosition
    // instead of a fixed property). Positions are resolved *relative to each
    // unit's own content*, not by the trivia group's bare name — the same
    // name (e.g. "trivia0") legitimately recurs at two different structural
    // positions inside one unit; see UnitTriviaPosition's docblock.
    // -------------------------------------------------------------------------

    #[Test]
    public function unitTriviaLeadingOnTheFirstUnitInsertsBeforeItsOwnIndent(): void
    {
        /** @var ObjectNode $object */
        $object = $this->parseValue("{\n    \"a\": 1,\n    \"b\": 2,\n    \"c\": 3\n}");
        $object->withMembersValidation();

        $object->insertIntoMembersTrivia(0, UnitTriviaPosition::Leading, 'about a', Placement::Before, 0);

        $leading = $object->getMemberUnit(0)[0];
        $this->assertInstanceOf(GroupAttribute::class, $leading);
        $comment = $leading->getNodes()[0];
        $this->assertInstanceOf(BlockCommentNode::class, $comment, 'No newline between the insertion point and "a": 1 — a line comment would swallow it.');
        $this->assertStringContainsString('/* about a */    "a": 1,', (string) $object);

        $reparsed = $this->parseValue((string) $object);
        $this->assertSame((string) $object, (string) $reparsed);
    }

    #[Test]
    public function unitTriviaLeadingOnAMiddleUnitFindsItsOwnLeadingGroupNotThePreviousUnitsTrailingComma(): void
    {
        /** @var ObjectNode $object */
        $object = $this->parseValue("{\n    \"a\": 1,\n    \"b\": 2,\n    \"c\": 3\n}");
        $object->withMembersValidation();

        // Middle unit (index 1): its "Leading" group sits *after* the comma
        // that closes the previous member — SequenceUnitTrivia must resolve
        // to that group, not the "trivia0" one shared with unit 0's tail
        // (see the class docblock on name ambiguity within a unit).
        $object->insertIntoMembersTrivia(1, UnitTriviaPosition::Leading, 'about b', Placement::Before, 0);

        $this->assertStringContainsString("\"a\": 1,\n/* about b */    \"b\": 2,", (string) $object);

        $reparsed = $this->parseValue((string) $object);
        $this->assertSame((string) $object, (string) $reparsed);
    }

    #[Test]
    public function unitTriviaTrailingOnTheLastUnitLandsBeforeTheClosingBrace(): void
    {
        /** @var ObjectNode $object */
        $object = $this->parseValue("{\n    \"a\": 1,\n    \"b\": 2,\n    \"c\": 3\n}");
        $object->withMembersValidation();

        // Last unit has no comma after its own content — Trailing (which
        // knows nothing about separators) must still resolve to whatever
        // group actually follows: the sequence's own closing trivia group.
        $object->insertIntoMembersTrivia(2, UnitTriviaPosition::Trailing, 'about c');

        $this->assertStringContainsString("\"c\": 3\n/* about c */}", (string) $object);

        $reparsed = $this->parseValue((string) $object);
        $this->assertSame((string) $object, (string) $reparsed);
    }

    #[Test]
    public function unitTriviaAfterStructuralCommaOnANonLastUnitInsertsRightAfterItsOwnComma(): void
    {
        /** @var ObjectNode $object */
        $object = $this->parseValue("{\n    \"a\": 1,\n    \"b\": 2,\n    \"c\": 3\n}");
        $object->withMembersValidation();

        // Default placement (append at the group's tail, after its existing
        // "\n") — nothing follows the insertion point, so the policy must
        // still fall back to a block comment. 'comma' is just the name JsonC
        // happens to give this token — insertIntoMembersTriviaAfterStructural()
        // has no built-in idea of what a separator is, see
        // [[project-trivia-insertion-policy]].
        $object->insertIntoMembersTriviaAfterStructural(0, 'comma', 'wiek');

        // getUnit()'s overlap means this same group is also reachable as unit 1's
        // "before comma" slot — asserting via the rendered text is unambiguous.
        $this->assertStringContainsString("\"a\": 1,\n/* wiek */    \"b\": 2,", (string) $object);

        $reparsed = $this->parseValue((string) $object);
        $this->assertSame((string) $object, (string) $reparsed);
    }

    #[Test]
    public function unitTriviaAfterStructuralCommaOnTheLastUnitThrowsBecauseThereIsNoTrailingComma(): void
    {
        /** @var ObjectNode $object */
        $object = $this->parseValue('{"a":1,"b":2}');
        $object->withMembersValidation();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("No structural attribute named 'comma' found");
        $object->insertIntoMembersTriviaAfterStructural(1, 'comma', 'oops');
    }

    #[Test]
    public function unitTriviaOnASingleMemberObjectStillChoosesABlockCommentEverywhere(): void
    {
        /** @var ObjectNode $object */
        $object = $this->parseValue('{"a":1}');
        $object->withMembersValidation();

        $object->insertIntoMembersTrivia(0, UnitTriviaPosition::Leading, 'x', Placement::Before, 0);

        $leading = $object->getMemberUnit(0)[0];
        $this->assertInstanceOf(GroupAttribute::class, $leading);
        $comment = $leading->getNodes()[0];
        $this->assertInstanceOf(BlockCommentNode::class, $comment);

        $reparsed = $this->parseValue((string) $object);
        $this->assertSame((string) $object, (string) $reparsed);
    }

    #[Test]
    public function insertIntoItemsTriviaOnAnArrayBehavesTheSameWayAsInsertIntoMembersTriviaOnAnObject(): void
    {
        /** @var ArrayNode $array */
        $array = $this->parseValue("[\n    1,\n    2,\n    3\n]");
        $array->withItemsValidation();

        $array->insertIntoItemsTriviaAfterStructural(1, 'comma', 'about second item');

        // Unit 1 is item "2" — lands after *its* comma, before item "3".
        $this->assertStringContainsString("2,\n/* about second item */    3", (string) $array);

        $reparsed = $this->parseValue((string) $array);
        $this->assertSame((string) $array, (string) $reparsed);
    }

    private function parseValue(string $json): ObjectNode|ArrayNode
    {
        $node = null;
        $this->assertGrammarParsing(
            string: $json,
            grammar: $this->grammar(),
            assertParsingResultValid: function ($result) use (&$node): void {
                /** @var JsonNode $result */
                $node = $result->getNodeValue();
            },
        );

        $this->assertNotNull($node);

        return $node;
    }
}
