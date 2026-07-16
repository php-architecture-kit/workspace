<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Func\Grammar\Json\Support;

use InvalidArgumentException;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\GroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\NodeAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\SequenceAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Structure\StructureAttribute;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Rfc8259\ArrayNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Rfc8259\MemberNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Rfc8259\ObjectNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Rfc8259\PrimitiveNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Rfc8259\PrimitiveType;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\InlineWsNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\LeadingWsNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\TrailingWsNode;
use stdClass;

/**
 * Builds a JsonRfc8259 ParsedTree from a native PHP value and applies whitespace
 * formatting to it. Exists because the generated ParsedTree classes alone are not
 * enough to produce formatted output — see format()/prettifySequence() docblocks
 * for the two gaps this class works around.
 */
final class TestJsonTreeBuilder
{
    public static function build(mixed $value): ObjectNode|ArrayNode|PrimitiveNode
    {
        if ($value instanceof stdClass) {
            $value = (array) $value;
            if ($value === []) {
                return ObjectNode::create();
            }
        }

        if (is_array($value)) {
            return array_is_list($value) ? self::buildArray($value) : self::buildObject($value);
        }

        if (is_bool($value)) {
            return PrimitiveNode::create($value ? PrimitiveType::True : PrimitiveType::False);
        }

        if ($value === null) {
            return PrimitiveNode::create(PrimitiveType::Null);
        }

        if (is_int($value) || is_float($value)) {
            return PrimitiveNode::create(PrimitiveType::Number, self::numberLiteral($value));
        }

        if (is_string($value)) {
            return PrimitiveNode::create(PrimitiveType::DoubleQuotedString, self::escapeString($value));
        }

        throw new InvalidArgumentException('Unsupported value of type ' . get_debug_type($value));
    }

    /**
     * Recursively re-derives whitespace (indentation, space-after-colon) on a
     * tree, from scratch, for the given style. Splitting build() from format()
     * mirrors the grammar: the shape (braces, commas, colons) and the trivia
     * (whitespace) are independent attribute slots.
     *
     * Idempotent and safe to call on ANY tree — freshly built, parsed, or
     * already formatted in some other style — because it always clears every
     * whitespace-carrying slot it touches before (re-)filling it; see
     * formatSequence()'s docblock for how it reconciles the two different
     * attribute shapes a "sequence of members/items" can have (build()'s,
     * which has no trivia1/trivia2 attributes at all, vs a parsed one, which
     * always has them, even empty).
     */
    public static function format(ObjectNode|ArrayNode|PrimitiveNode $node, TestJsonFormat $style, int $depth = 0): void
    {
        if ($node instanceof ObjectNode) {
            self::formatOuterTrivia($node->trivia0, $node->trivia1, $node->getMembers() !== [], $style, $depth);

            foreach ($node->getMembers() as $member) {
                // trivia0 (between the key and the colon) was never cleared here —
                // a latent gap, found via a Json5 messy-fixture regression (extra
                // whitespace before a colon survived formatting unchanged); see
                // TestJson5TreeBuilder::format()'s docblock for the same fix there.
                $member->trivia0->nodes = [];
                $member->trivia1->nodes = $style === TestJsonFormat::Minified ? [] : [InlineWsNode::create(' ')];
                self::format($member->getNodeValue(), $style, $depth + 1);
            }
            self::formatSequence($node->members, 'member', $style, $depth);
        } elseif ($node instanceof ArrayNode) {
            self::formatOuterTrivia($node->trivia0, $node->trivia1, $node->getItems() !== [], $style, $depth);

            foreach ($node->getItems() as $item) {
                self::format($item, $style, $depth + 1);
            }
            self::formatSequence($node->items, 'item', $style, $depth);
        }
    }

    /**
     * beginObject is followed by TWO adjacent optional whitespace slots:
     * ObjectNode's own outer trivia0 ('-t*'), then (only if there is at least one
     * member) the members SequenceAttribute's own inner leading trivia ('-l*') —
     * see withRootSequence() in JsonRfc8259: "beginObject -t* ?(-l* member
     * ...)[members] -l* endObject" (ArrayNode is the same shape). Since a
     * core-framework fix (RegionConfigApi::withPossibleNamesForTag(), wired
     * through TagToChoiceCompiler/SequenceNodeEnricher), '-t' and '-l' resolve to
     * disjoint sets of whitespace sub-kinds: a run spanning a newline always
     * tokenizes as a trailingWs region (kept by '-t') followed by a leadingWs
     * region (kept by '-l'). So a real parse always puts the "\n" in the OUTER
     * slot and the indent in the INNER one — confirmed via `bin/console
     * parser:parse` on both a top-level and a nested pretty object. This mirrors
     * that split exactly, rather than (as an earlier version of this method did)
     * dumping the whole "\n<indent>" run into the inner slot alone: same output
     * text either way, but this keeps a built/formatted tree's attribute *shape*
     * indistinguishable from a real parse's, which matters if anything ever walks
     * the tree structurally instead of just calling __toString() on it.
     *
     * The closing side is the mirror image: the sequence's own trailing slot
     * keeps only the "\n", and the *outer* trivia1 here gets the indent needed to
     * align the closing brace/bracket with its own nesting level — empty at
     * depth 0, matching real parses (no indent needed before a top-level "}").
     */
    private static function formatOuterTrivia(GroupAttribute $leading, GroupAttribute $trailing, bool $hasContent, TestJsonFormat $style, int $depth): void
    {
        $leading->nodes = [];
        $trailing->nodes = [];

        if ($style === TestJsonFormat::Minified || !$hasContent) {
            return;
        }

        $leading->nodes = [TrailingWsNode::create("\n")];

        $outerIndent = str_repeat($style->indentUnit(), $depth);
        if ($outerIndent !== '') {
            $trailing->nodes = [LeadingWsNode::create($outerIndent)];
        }
    }

    public static function buildFormatted(mixed $value, TestJsonFormat $style): ObjectNode|ArrayNode|PrimitiveNode
    {
        $node = self::build($value);
        self::format($node, $style);

        return $node;
    }

    /**
     * ObjectNode::addMember() (SequenceCarrier::addUnit()) only works on a tree
     * built via ObjectNode::create(): a *parsed* object's members SequenceAttribute
     * always ends with a materialized trailing `whitespace*[trivia1]` group — even
     * when empty, e.g. for "{"a":1,"b":2}" — because the matcher fills in every
     * optional slot the grammar defines, unlike addUnit()'s lazy auto-factories
     * (see prettifySequence()'s docblock). withMembersValidation()'s NestedSequence
     * model treats that trailing slot as the end of the (non-repeating) `?(...)`
     * group, so replaying it leaves the validity cursor "complete" and addUnit()
     * rejects a further 'member'. This appends by splicing the attribute list
     * directly — bypassing the validity cursor — so it works on both a freshly
     * built and an already-parsed object alike.
     */
    public static function appendMember(ObjectNode $object, MemberNode $member): void
    {
        self::appendContentUnit($object->members, new NodeAttribute('member', $member->setParent($object)));
    }

    /** @see appendMember() — same limitation, same workaround, for ArrayNode. */
    public static function appendItem(ArrayNode $array, ObjectNode|ArrayNode|PrimitiveNode $item): void
    {
        self::appendContentUnit($array->items, new NodeAttribute('item', $item->setParent($array)));
    }

    private static function appendContentUnit(SequenceAttribute $seq, NodeAttribute $content): void
    {
        $attributes = $seq->attributes;

        // Keep the trailing whitespace*[trivia1] slot (the pretty-printed indent
        // for the closing brace/bracket) at the very end, after the new content.
        $trailingTrivia = null;
        if ($attributes !== [] && end($attributes) instanceof GroupAttribute) {
            $trailingTrivia = array_pop($attributes);
        }

        if ($attributes !== []) {
            $attributes[] = new GroupAttribute('trivia0', []);
            $attributes[] = new StructureAttribute(true, 'comma', ',');
            $attributes[] = new GroupAttribute('trivia1', []);
            $attributes[] = new GroupAttribute('trivia2', []);
        }
        $attributes[] = $content;

        if ($trailingTrivia !== null) {
            $attributes[] = $trailingTrivia;
        }

        $seq->attributes = $attributes;
    }

    /**
     * ObjectNode::addMember()/ArrayNode::addItem() go through SequenceCarrier::addUnit(),
     * whose auto-factories insert a structural attribute only when the grammar leaves no
     * way to reach the next content unit without it (see SequenceCarrier::addUnit). Since
     * every trivia slot in the members/items nested sequence is `whitespace*` (min 0), it
     * is always skippable, so addUnit() never inserts trivia — a freshly built object with
     * N members has *no* trivia0/trivia1/trivia2 attributes at all, only
     * "member, trivia0(empty), comma, member, ...". A *parsed* sequence, by contrast,
     * always has every slot the grammar defines, even empty ones — see
     * appendContentUnit()'s docblock for the attribute dump proving it.
     *
     * Each anchor needs a small, ordered queue of fills — 'newline' then 'indent'
     * after a comma (matching the real trivia1/trivia2 split), just 'indent' before
     * the first content unit (the "\n" there belongs to the *outer* node's own
     * trivia0 — see formatOuterTrivia()), just 'newline' trailing after the last
     * content unit (its indent likewise belongs to the outer node's own trivia1).
     * To handle both the build() shape (no groups to reuse) and the parsed shape
     * (groups already present, even empty) AND be idempotent (safe to call again on
     * an already-formatted sequence), this walks the sequence once, clearing every
     * whitespace-carrying GroupAttribute it passes, and drains the pending queue
     * into each GroupAttribute encountered at an anchor — reusing it if one exists,
     * inserting a brand new one (correctly named trivia0/trivia1/trivia2 to match
     * what a real parse would call it) if the queue isn't empty by the time content
     * is reached.
     */
    private static function formatSequence(SequenceAttribute $seq, string $contentName, TestJsonFormat $style, int $depth): void
    {
        if ($seq->attributes === []) {
            return;
        }

        $minified = $style === TestJsonFormat::Minified;
        $innerIndent = str_repeat($style->indentUnit(), $depth + 1);

        $rebuilt = [];
        /** @var list<array{string,string}> $pending */
        $pending = $minified ? [] : [['trivia0', 'indent']];

        foreach ($seq->attributes as $attribute) {
            if ($attribute instanceof GroupAttribute) {
                $attribute->nodes = [];
                if ($pending !== []) {
                    [, $kind] = array_shift($pending);
                    self::fillGroup($attribute, $kind, $innerIndent);
                }
                $rebuilt[] = $attribute;
                continue;
            }

            if ($attribute instanceof NodeAttribute && $attribute->getName() === $contentName) {
                while ($pending !== []) {
                    [$name, $kind] = array_shift($pending);
                    $rebuilt[] = self::newFilledGroup($name, $kind, $innerIndent);
                }
                $rebuilt[] = $attribute;
                continue;
            }

            if ($attribute instanceof StructureAttribute && $attribute->getName() === 'comma') {
                $rebuilt[] = $attribute;
                $pending = $minified ? [] : [['trivia1', 'newline'], ['trivia2', 'indent']];
                continue;
            }

            $rebuilt[] = $attribute;
        }

        // Trailing anchor: the sequence's own final slot gets only the newline —
        // its indent is the outer node's own trivia1, filled by formatOuterTrivia().
        if (!$minified) {
            $last = $rebuilt === [] ? null : $rebuilt[array_key_last($rebuilt)];
            if ($last instanceof GroupAttribute && $last->nodes === []) {
                self::fillGroup($last, 'newline', $innerIndent);
            } else {
                $rebuilt[] = self::newFilledGroup('trivia1', 'newline', $innerIndent);
            }
        }

        $seq->attributes = $rebuilt;
    }

    /**
     * A "\n<indent>" run is always split as two separate whitespace nodes/slots,
     * never one, because that is what the tokenizer itself produces (see
     * Whitespace's newline token: it always closeRegion()s, so a run spanning a
     * newline is split into a trailingWs region ending at the newline and a fresh
     * leadingWs region after it — never one region straddling both).
     */
    private static function fillGroup(GroupAttribute $group, string $kind, string $indent): void
    {
        $group->nodes = match ($kind) {
            'newline' => [TrailingWsNode::create("\n")],
            'indent' => $indent !== '' ? [LeadingWsNode::create($indent)] : [],
        };
    }

    private static function newFilledGroup(string $name, string $kind, string $indent): GroupAttribute
    {
        $group = new GroupAttribute($name, []);
        self::fillGroup($group, $kind, $indent);

        return $group;
    }

    /** @param array<int,mixed> $value */
    private static function buildArray(array $value): ArrayNode
    {
        $array = ArrayNode::create();
        foreach ($value as $item) {
            $array->addItem(self::build($item));
        }

        return $array;
    }

    /** @param array<string,mixed> $value */
    private static function buildObject(array $value): ObjectNode
    {
        $object = ObjectNode::create();
        foreach ($value as $key => $item) {
            $object->addMember(MemberNode::create(self::escapeString((string) $key), self::build($item)));
        }

        return $object;
    }

    private static function numberLiteral(int|float $value): string
    {
        if (is_float($value) && !is_finite($value)) {
            throw new InvalidArgumentException('JSON numbers cannot be NAN/INF.');
        }

        return (string) $value;
    }

    /**
     * The generated PrimitiveNode/MemberNode do NOT escape their $content — it is
     * inserted verbatim between the surrounding quotes (see RawRegionAttribute::
     * __toString()). A caller building a string value or object key from arbitrary
     * data must escape it first, or produce invalid/broken JSON.
     */
    private static function escapeString(string $value): string
    {
        $escaped = str_replace(
            ['\\', '"', "\n", "\r", "\t", "\x08", "\x0C"],
            ['\\\\', '\\"', '\\n', '\\r', '\\t', '\\b', '\\f'],
            $value,
        );

        return preg_replace_callback(
            '/[\x00-\x1F]/',
            static fn(array $m): string => sprintf('\\u%04x', ord($m[0])),
            $escaped,
        );
    }
}
