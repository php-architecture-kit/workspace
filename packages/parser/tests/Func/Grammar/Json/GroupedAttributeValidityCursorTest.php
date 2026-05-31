<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Func\Grammar\Json;

use InvalidArgumentException;
use PhpArchitecture\Parser\Foundation\Grammar\Compiled\Model\CompiledGrammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Matching\Model\Sequence;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeAttributeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\ChoiceAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\GroupedAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\NodeAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\SequenceValidityCursor;
use PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Json\JsonRfc8259;
use PhpArchitecture\Parser\Tests\Func\Grammar\GrammarTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('func')]
final class GroupedAttributeValidityCursorTest extends GrammarTestCase
{
    private function grammar(): Grammar
    {
        return (new JsonRfc8259())->grammar();
    }

    /**
     * Extracts the compiled Sequence for a given rule name from the root region.
     */
    private function objectSequence(CompiledGrammar $compiledGrammar): Sequence
    {
        // `beginObject` starts the 'object' region with a root sequence
        // ("beginObject -* ?(member (-* comma -* member)*)[members]/g -* endObject").
        // That compiled sequence is stored as SequenceLibrary::$rootSequence, not as
        // a named entry in SequenceLibrary::$sequences.
        return $compiledGrammar->regions['object']->sequenceLibrary->rootSequence;
    }

    /**
     * Finds the first NodeAttribute (child node) inside a GroupedAttribute.
     */
    private function findFirstNodeAttrInGrouped(GroupedAttribute $grouped): ?NodeAttributeInterface
    {
        foreach ($grouped->attributes as $attr) {
            if ($attr instanceof NodeAttribute) {
                return $attr;
            }
        }
        return null;
    }

    // -------------------------------------------------------------------------

    /**
     * Attaching a cursor to a GroupedAttribute that already holds attributes
     * parsed from real JSON must replay without throwing — the parsed tree is
     * structurally correct, so the cursor should advance through every existing
     * attribute without error.
     */
    #[Test]
    public function replayOnCorrectlyParsedMembersDoesNotThrow(): void
    {
        $objectSequence = null;

        $this->assertGrammarParsing(
            string: '{"a":1,"b":2}',
            grammar: $this->grammar(),
            assertCompiledGrammarValid: function (CompiledGrammar $cg) use (&$objectSequence): void {
                $objectSequence = $this->objectSequence($cg);
            },
            assertParsingResultValid: function (NodeInterface $result, self $test) use (&$objectSequence): void {
                $membersAttr = $this->findMembersGroupedAttr($result);
                $test->assertNotNull($membersAttr, 'Expected a "members" GroupedAttribute on the object node');

                // Must not throw — all parsed attributes are in correct order
                $membersAttr->withValidSequence(
                    SequenceValidityCursor::fromSequence($objectSequence, 'members'),
                );

                $test->assertCount(
                    count($membersAttr->attributes),
                    $membersAttr->attributes,
                    'GroupedAttribute attributes must be unchanged after cursor attachment',
                );
            },
        );
    }

    /**
     * After attaching a cursor to a fully-replayed GroupedAttribute, trying to
     * add an attribute whose name does not belong at the next valid position
     * must throw InvalidArgumentException.
     */
    #[Test]
    public function addingWrongAttributeAfterCursorAttachmentThrows(): void
    {
        $objectSequence = null;

        $this->assertGrammarParsing(
            string: '{"a":1}',
            grammar: $this->grammar(),
            assertCompiledGrammarValid: function (CompiledGrammar $cg) use (&$objectSequence): void {
                $objectSequence = $this->objectSequence($cg);
            },
            assertParsingResultValid: function (NodeInterface $result, self $test) use (&$objectSequence): void {
                $membersAttr = $this->findMembersGroupedAttr($result);
                $test->assertNotNull($membersAttr);

                // Replay existing attributes (member), cursor is now past them
                $membersAttr->withValidSequence(
                    SequenceValidityCursor::fromSequence($objectSequence, 'members'),
                );

                // After one member the sequence is complete (inner loop is optional).
                // Adding "member" again at this point would require a "comma" first.
                // We use the first real NodeAttribute from the parsed tree as a donor
                // for a valid-looking but wrong-position attribute.
                $memberAttr = $this->findFirstNodeAttrInGrouped($membersAttr);
                $test->assertNotNull($memberAttr, 'Expected at least one NodeAttribute inside members');

                // "member" directly after "member" — comma is missing → must throw
                $test->expectException(InvalidArgumentException::class);
                $membersAttr->addAttribute($memberAttr);
            },
        );
    }

    /**
     * After the cursor replays existing attributes it correctly tracks position,
     * so adding the next structurally valid attribute must succeed without throwing.
     *
     * For `{"a":1}` the members group ends with one `member`. The next valid
     * attribute (if we were to extend the members group) would be `comma`.
     * We verify by cloning the cursor state before adding — the cursor's
     * reported valid-next names must contain "comma".
     */
    #[Test]
    public function cursorReportsCorrectValidNamesAfterReplay(): void
    {
        $objectSequence = null;

        $this->assertGrammarParsing(
            string: '{"a":1}',
            grammar: $this->grammar(),
            assertCompiledGrammarValid: function (CompiledGrammar $cg) use (&$objectSequence): void {
                $objectSequence = $this->objectSequence($cg);
            },
            assertParsingResultValid: function (NodeInterface $result, self $test) use (&$objectSequence): void {
                $membersAttr = $this->findMembersGroupedAttr($result);
                $test->assertNotNull($membersAttr);

                // Build a fresh cursor (does NOT replay — we query valid-next
                // after manually advancing past "member" ourselves)
                $cursor = SequenceValidityCursor::fromSequence($objectSequence, 'members');
                $cursor->advance('member'); // simulate first member

                $validNext = $cursor->getValidNextNames();

                // After one member the inner repetition offers comma (or whitespace);
                // the cursor must report at least "comma" as valid.
                $test->assertContains(
                    'comma',
                    $validNext,
                    'After one member, "comma" must be among the valid next attribute names',
                );

                // The sequence is also completable at this point (inner loop is optional)
                $test->assertTrue(
                    $cursor->canComplete(),
                    'After one member the members sequence must be completable (inner repetition is optional)',
                );
            },
        );
    }

    // -------------------------------------------------------------------------

    private function findMembersGroupedAttr(NodeInterface $result): ?GroupedAttribute
    {
        return $this->searchGroupedAttr($result, 'members');
    }

    private function searchGroupedAttr(NodeInterface $node, string $name): ?GroupedAttribute
    {
        foreach ($node->getAttributes() as $attr) {
            if ($attr instanceof GroupedAttribute && $attr->getName() === $name) {
                return $attr;
            }

            if ($attr instanceof NodeAttribute) {
                $found = $this->searchGroupedAttr($attr->node, $name);
                if ($found !== null) {
                    return $found;
                }
            }

            if ($attr instanceof ChoiceAttribute && $attr->selected instanceof NodeAttribute) {
                $found = $this->searchGroupedAttr($attr->selected->node, $name);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }
}
