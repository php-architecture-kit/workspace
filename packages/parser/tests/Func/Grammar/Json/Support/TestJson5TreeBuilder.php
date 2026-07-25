<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Func\Grammar\Json\Support;

use InvalidArgumentException;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\GroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\NodeAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\SequenceAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Structure\StructureAttribute;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Ver5\ArrayNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Ver5\IdentifierType;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Ver5\MemberNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Ver5\ObjectNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Ver5\PrimitiveNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Ver5\PrimitiveType;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\InlineWsNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\LeadingWsNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\TrailingWsNode;
use stdClass;

/**
 * Json5 analogue of TestJsonTreeBuilder (Json\Rfc8259) — not a straight reuse
 * because Json5's MemberNode::create() takes an extra IdentifierType
 * parameter (unquoted/double/single-quoted keys are all valid JSON5, see
 * IdentifierType) and every generated class lives in a different carve-out
 * namespace (Json\Ver5\*, not Json\Rfc8259\*). build() always produces
 * double-quoted keys/strings — the safe, universally-valid subset — even
 * though a *parsed* Json5 tree may have unquoted or single-quoted ones;
 * editing such a tree in place keeps whatever identifier style was already
 * there (see MemberNode::setIdentifier()) rather than normalizing it.
 *
 * format()/formatSequence() are structurally identical to TestJsonTreeBuilder's
 * (same grammar shape: outer trivia0/trivia1 split, members' own
 * trivia0/comma/trivia1/trivia2 loop) — Json5 additionally allows a trailing
 * comma before the closing bracket (an optional `trailingComma` structural
 * attribute + its own `trivia` leading group, see ObjectNode::
 * membersValidity()), which addMember()/addItem() never emits (same
 * auto-factory laziness as TestJsonTreeBuilder's build() path — see its
 * docblock), so format() never needs to manage it for a *built* tree. A
 * *parsed* tree that already has a trailing comma keeps it untouched by
 * formatSequence()'s catch-all branch — not reformatted, but not dropped
 * either.
 */
final class TestJson5TreeBuilder
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

    /** @see TestJsonTreeBuilder::format() — identical shape/reasoning, Json5 namespace. */
    public static function format(ObjectNode|ArrayNode|PrimitiveNode $node, TestJsonFormat $style, int $depth = 0): void
    {
        if ($node instanceof ObjectNode) {
            self::formatOuterTrivia($node->trivia0, $node->trivia1, $node->getMembers() !== [], $style, $depth);

            foreach ($node->getMembers() as $member) {
                // trivia0 (between the key and the colon) is cleared unconditionally in
                // every style — matching json_encode's convention of no space before
                // the colon. A real *parsed* document can hold whitespace there (JSON5
                // in particular, since unquoted-identifier keys make it easy to type),
                // which format() must strip just like every other whitespace-carrying
                // slot it touches — this was silently missed until a real messy .json5
                // fixture (extra spaces before a colon) exposed it.
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

    /** @see TestJsonTreeBuilder::formatOuterTrivia() — identical shape/reasoning. */
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

    /** @see TestJsonTreeBuilder::appendMember() */
    public static function appendMember(ObjectNode $object, MemberNode $member): void
    {
        self::appendContentUnit($object->members, new NodeAttribute('member', $member->setParent($object)));
    }

    /** @see TestJsonTreeBuilder::appendItem() */
    public static function appendItem(ArrayNode $array, ObjectNode|ArrayNode|PrimitiveNode $item): void
    {
        self::appendContentUnit($array->items, new NodeAttribute('item', $item->setParent($array)));
    }

    private static function appendContentUnit(SequenceAttribute $seq, NodeAttribute $content): void
    {
        $attributes = $seq->attributes;

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

    /** @see TestJsonTreeBuilder::formatSequence() */
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
            $object->addMember(MemberNode::create(IdentifierType::DoubleQuotedString, self::escapeString((string) $key), self::build($item)));
        }

        return $object;
    }

    private static function numberLiteral(int|float $value): string
    {
        if (is_float($value) && !is_finite($value)) {
            throw new InvalidArgumentException('Use PrimitiveType::Infinity/Nan for non-finite values, not a Number literal.');
        }

        return (string) $value;
    }

    /** @see TestJsonTreeBuilder::escapeString() */
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
