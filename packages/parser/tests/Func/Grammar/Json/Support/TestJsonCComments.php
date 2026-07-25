<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Func\Grammar\Json\Support;

use LogicException;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeAttributeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\Placement;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\GroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\NodeAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\OptionalRawAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawContentAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawRegionAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Structure\StructureAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeOrigin;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\C\ArrayNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\C\BlockCommentNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\C\JsonNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\C\LineCommentNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\C\ObjectNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Comment\SingleLineNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\InlineWsNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\LeadingWsNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\TrailingWsNode;

/**
 * Inserts comments (JsonC's `//`/`/* *‍/`) into an already-parsed JSON tree at
 * various positions, deriving indentation from the surrounding tree instead of
 * requiring the caller to type it. Companion to TestJsonTreeBuilder (plain JSON) —
 * see its class docblock for the trivia/format gaps this session's earlier
 * exploration found; this one documents the *comment*-specific findings on
 * top of those (see [[project-jsonc-comment-insertion]]):
 *
 * 1. `LineCommentNode::create()`/`SingleLineNode::create()` used to build a
 *    shorter attribute list than these classes' own generated property hooks
 *    expected (`$content` indexes attributes[2]/[3], but create() put the
 *    RawContentAttribute at [1]/[2]) — calling getRawContent()/setRawContent()
 *    on a freshly ::create()'d comment threw a TypeError. Root cause: the
 *    Tree Generator's attribute-kind dispatch (FacadeClassRenderer,
 *    NodeSchemaCollector, AttributeSchema) had no case at all for
 *    OptionalRawAttribute (the type behind a comment's own optional
 *    leadingWs/trailingWs slot), so such a slot was silently skipped both in
 *    create() and in accessor generation. **Fixed** (this session) by adding
 *    that missing case — additive only, so nothing that worked before changed;
 *    every node with such a slot was already broken, so there was nothing to
 *    "break further". Classes regenerated via `parser:tree:generate` (see each
 *    namespace's GENERATED.md). line() now delegates straight to
 *    LineCommentNode::create() — see JsonCTreeBuilderTest::
 *    createdLineCommentNowHasAWorkingContentAccessor() for the confirming test.
 * 2. `SingleLineNode::create()` *still* hardcodes both asterisks present
 *    (`openingAsterisk`/`closingAsterisk`) — a separate, out-of-scope gap
 *    (StructureAttribute has no notion of "optionally present" in create()
 *    at all, unrelated to the OptionalRawAttribute fix above) — so it can
 *    only ever build `/** ... *‍/`-style comments, never a plain `/* ... *‍/`,
 *    even though real .jsonc files use both styles (see block_comments.jsonc).
 *    block() still builds the plain (asterisk-less) shape by hand for this
 *    reason.
 * 3. The JsonC grammar itself could not parse an own-line comment at all
 *    before this session's fix to JsonC.php's root sequence (widened the
 *    '-l*' slots to bare '-*' — narrow '-l*' excludes the trailingWs that
 *    always closes a comment's own line, so `{\n    // c\n    "a":1}` failed
 *    to match). See JsonC.php's docblock and
 *    [[project-jsonc-comment-insertion]].
 */
final class TestJsonCComments
{
    public static function line(string $text): LineCommentNode
    {
        return LineCommentNode::create(leadingWs: ' ', content: $text);
    }

    public static function block(string $text): BlockCommentNode
    {
        $singleLine = new SingleLineNode(
            name: 'singleLine',
            origin: NodeOrigin::Sequence,
            attributes: [
                new StructureAttribute(true, 'blockCommentStart', '/*'),
                new StructureAttribute(false, 'openingAsterisk', ''),
                new OptionalRawAttribute(
                    new RawRegionAttribute(opener: null, content: ' ', closer: null, name: 'inlineWs', anchorName: 'leadingWs'),
                    name: 'leadingWs',
                    anchorName: 'leadingWs',
                ),
                new RawContentAttribute($text, name: 'raw', anchorName: 'content'),
                new OptionalRawAttribute(
                    new RawRegionAttribute(opener: null, content: ' ', closer: null, name: 'inlineWs', anchorName: 'trailingWs'),
                    name: 'trailingWs',
                    anchorName: 'trailingWs',
                ),
                new StructureAttribute(false, 'closingAsterisk', ''),
                new StructureAttribute(true, 'blockCommentEnd', '*/'),
            ],
            parent: null,
        );

        return BlockCommentNode::create($singleLine);
    }

    /**
     * Inserts $comment on its own line immediately before the member/item at
     * $index, matching that member/item's own existing indentation — read
     * from the LeadingWsNode already present in its unit (never hand-typed by
     * the caller).
     */
    public static function prependOwnLineComment(ObjectNode|ArrayNode $container, int $index, LineCommentNode|BlockCommentNode $comment): void
    {
        $group = self::leadingGroupOf($container, $index);
        $indent = self::deriveIndent($group);

        // The group's existing content already supplies the indent for what
        // comes *after* our insertion (that's precisely what we just read it
        // from) — only prepend the comment's own leading indent + trailing
        // newline, never a second copy of the indent, or the member would end
        // up double-indented.
        $prefix = $indent !== '' ? [LeadingWsNode::create($indent)] : [];
        $prefix[] = $comment;
        $prefix[] = TrailingWsNode::create("\n");
        array_splice($group->nodes, 0, 0, $prefix);
    }

    /**
     * Inserts $comment right after the member/item at $index's own value, on
     * the same line, before the following comma/closing bracket — e.g.
     * `"key": value /* comment *‍/,`.
     */
    public static function appendTrailingComment(ObjectNode|ArrayNode $container, int $index, LineCommentNode|BlockCommentNode $comment): void
    {
        $group = self::trailingGroupOf($container, $index);
        self::guardLineCommentHasALineBreakToLandOn($group, $comment);

        // Prepend, not append: for the *last* member/item this group is the
        // sequence's own final trailing trivia, which for a pretty-printed
        // document already holds "\n" (the line leading into the closing
        // bracket) — appending after it would put the comment on its own new
        // line instead of right after the value.
        array_splice($group->nodes, 0, 0, [InlineWsNode::create(' '), $comment]);
    }

    /**
     * Inserts $comment right after the comma that follows the member/item at
     * $index, before the newline leading into the next one — e.g.
     * `"age": 30, // wiek`. 'comma' is just JsonC's own structural token name
     * for this grammar — hardcoded here rather than taken as a parameter,
     * since this helper (unlike the framework's generic node classes) is
     * already JsonC-specific.
     */
    public static function appendAfterComma(ObjectNode|ArrayNode $container, int $index, LineCommentNode|BlockCommentNode $comment): void
    {
        $group = self::groupAfterComma(self::unit($container, $index));
        self::guardLineCommentHasALineBreakToLandOn($group, $comment);

        array_splice($group->nodes, 0, 0, [InlineWsNode::create(' '), $comment]);
    }

    /**
     * A '//' comment runs to the end of its line, consuming everything after
     * it — comma, closing bracket, next member, all of it — until a real
     * newline. Both appendTrailingComment() and appendAfterComma() insert at
     * the group's own front, so "is there a line break after the insertion
     * point" reduces to "does the group's *existing* content hold one"; if
     * the group is empty (a minified document) or holds only inline
     * whitespace, there is nothing to stop the comment from swallowing real
     * structure. Caught here instead of left to silently produce a
     * corrupt-but-plausible-looking .jsonc file — see
     * JsonCTreeBuilderTest::appendTrailingLineCommentBeforeAFollowingCommaIsRejected...().
     * A BlockCommentNode never has this problem (it closes itself with '*‍/').
     */
    private static function guardLineCommentHasALineBreakToLandOn(GroupAttribute $group, LineCommentNode|BlockCommentNode $comment): void
    {
        if ($comment instanceof LineCommentNode && !self::containsLineBreak($group)) {
            throw new LogicException(
                "A line comment ('//') needs an existing line break after the insertion point, or it would swallow whatever structurally follows on the same line (comma, closing bracket, ...). Use TestJsonCComments::block() here instead.",
            );
        }
    }

    private static function containsLineBreak(GroupAttribute $group): bool
    {
        foreach ($group->getNodes() as $node) {
            if (str_contains((string) $node, "\n")) {
                return true;
            }
        }

        return false;
    }

    /**
     * getMemberUnit()/getItemUnit() require withMembersValidation()/
     * withItemsValidation() to have been called first (that's what populates
     * contentOffsets) — a real parse never calls it automatically. Calling it
     * again here is safe/idempotent (it just replays $attributes into a fresh
     * cursor), so callers don't need to remember to do it themselves before
     * using this helper.
     *
     * @return NodeAttributeInterface[]
     */
    private static function unit(ObjectNode|ArrayNode $container, int $index): array
    {
        if ($container instanceof ObjectNode) {
            $container->withMembersValidation();
            return $container->getMemberUnit($index);
        }

        $container->withItemsValidation();
        return $container->getItemUnit($index);
    }

    /**
     * getUnit($index) returns the unit's own content attribute (member/item)
     * together with whatever structural attributes (trivia, comma, ...)
     * surround it on both sides — see SequenceCarrier::getUnit()'s own
     * docblock. "Leading" is simply the first trivia group in that slice,
     * "Trailing" the last — this holds uniformly for the first/middle/last
     * unit without special-casing: for the *last* unit specifically,
     * getUnit()'s own end boundary already extends through to the sequence's
     * own final trailing trivia (there is no separate cycle after the last
     * content attribute), so the "last GroupAttribute in the slice" is
     * automatically the right one there too.
     */
    private static function leadingGroupOf(ObjectNode|ArrayNode $container, int $index): GroupAttribute
    {
        return self::firstGroupIn(self::unit($container, $index));
    }

    private static function trailingGroupOf(ObjectNode|ArrayNode $container, int $index): GroupAttribute
    {
        return self::lastGroupIn(self::unit($container, $index));
    }

    /** @param NodeAttributeInterface[] $unit */
    private static function firstGroupIn(array $unit): GroupAttribute
    {
        foreach ($unit as $attr) {
            if ($attr instanceof GroupAttribute) {
                return $attr;
            }
        }

        throw new LogicException('No trivia group found in this unit.');
    }

    /** @param NodeAttributeInterface[] $unit */
    private static function lastGroupIn(array $unit): GroupAttribute
    {
        for ($i = count($unit) - 1; $i >= 0; $i--) {
            if ($unit[$i] instanceof GroupAttribute) {
                return $unit[$i];
            }
        }

        throw new LogicException('No trivia group found in this unit.');
    }

    /**
     * The trivia group right after the 'comma' that follows this unit's own
     * content attribute — a unit's slice can start with a comma left over
     * from the *previous* unit's own separator, so the scan for "comma"
     * starts only after the content attribute, not from the front of the
     * slice.
     *
     * @param NodeAttributeInterface[] $unit
     */
    private static function groupAfterComma(array $unit): GroupAttribute
    {
        $contentIndex = null;
        foreach ($unit as $i => $attr) {
            if ($attr instanceof NodeAttribute) {
                $contentIndex = $i;
                break;
            }
        }

        for ($i = ($contentIndex ?? -1) + 1; $i < count($unit); $i++) {
            if ($unit[$i] instanceof StructureAttribute && $unit[$i]->getName() === 'comma') {
                for ($j = $i + 1; $j < count($unit); $j++) {
                    if ($unit[$j] instanceof GroupAttribute) {
                        return $unit[$j];
                    }
                }
            }
        }

        throw new LogicException("No structural attribute named 'comma' found after this unit's own content.");
    }

    public static function prependFileComment(JsonNode $root, LineCommentNode|BlockCommentNode $comment): void
    {
        $root->trivia0->nodes = [$comment, TrailingWsNode::create("\n"), ...$root->trivia0->nodes];
    }

    public static function appendFileComment(JsonNode $root, LineCommentNode|BlockCommentNode $comment): void
    {
        $root->trivia1->nodes = [...$root->trivia1->nodes, TrailingWsNode::create("\n"), $comment];
    }

    private static function deriveIndent(GroupAttribute $group): string
    {
        foreach ($group->getNodes() as $node) {
            if ($node instanceof LeadingWsNode) {
                return $node->getRawLeadingWs();
            }
        }
        return '';
    }
}
