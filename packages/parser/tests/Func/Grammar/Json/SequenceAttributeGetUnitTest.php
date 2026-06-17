<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Func\Grammar\Json;

use OutOfRangeException;
use PhpArchitecture\Parser\Foundation\Grammar\Compiled\Model\CompiledGrammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Matching\Model\Sequence;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeAttributeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\ChoiceAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\GroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\SequenceAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\NodeAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\SequenceValidityCursor;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Structure\StructureAttribute;
use PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Json\JsonRfc8259;
use PhpArchitecture\Parser\Tests\Func\Grammar\GrammarTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('func')]
final class SequenceAttributeGetUnitTest extends GrammarTestCase
{
    private function grammar(): Grammar
    {
        return (new JsonRfc8259())->grammar();
    }

    private function objectSequence(CompiledGrammar $compiledGrammar): Sequence
    {
        return $compiledGrammar->regions['object']->sequenceLibrary->rootSequence;
    }

    /** @return int[] positions of NodeAttribute('member') in $attrs */
    private function findMemberPositions(array $attrs): array
    {
        $positions = [];
        foreach ($attrs as $i => $attr) {
            if ($attr instanceof NodeAttribute && $attr->getName() === 'member') {
                $positions[] = $i;
            }
        }
        return $positions;
    }

    private function attachValidSequence(SequenceAttribute $membersAttr, Sequence $objectSequence): void
    {
        $membersAttr->withValidSequence(
            SequenceValidityCursor::fromSequence($objectSequence, 'members'),
            [
                'trivia0' => static fn() => new GroupAttribute('trivia0', []),
                'comma'   => static fn() => new StructureAttribute(true, 'comma', ','),
                'trivia1' => static fn() => new GroupAttribute('trivia1', []),
            ],
        );
    }

    private function findMembersGroupedAttr(NodeInterface $node): ?SequenceAttribute
    {
        foreach ($node->getAttributes() as $attr) {
            if ($attr instanceof SequenceAttribute && $attr->getName() === 'members') {
                return $attr;
            }
            if ($attr instanceof NodeAttribute) {
                $found = $this->findMembersGroupedAttr($attr->node);
                if ($found !== null) {
                    return $found;
                }
            }
            if ($attr instanceof ChoiceAttribute && $attr->selected instanceof NodeAttribute) {
                $found = $this->findMembersGroupedAttr($attr->selected->node);
                if ($found !== null) {
                    return $found;
                }
            }
        }
        return null;
    }

    // -------------------------------------------------------------------------
    // Scenariusz 1: 1 unit
    // -------------------------------------------------------------------------

    /**
     * For a single-member object, getUnit(0) must return exactly the one member
     * attribute — the entire attributes array.
     */
    #[Test]
    public function getUnitWithSingleMemberReturnsMemberAttributeOnly(): void
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
                $test->assertNotNull($membersAttr, 'Expected a "members" SequenceAttribute');

                $this->attachValidSequence($membersAttr, $objectSequence);

                $test->assertSame(1, $membersAttr->getUnitCount());

                $attrs = $membersAttr->attributes;
                $unit0 = $membersAttr->getUnit(0);

                $test->assertCount(1, $unit0, 'Single-member object: unit must contain exactly 1 attribute');
                $test->assertSame($attrs[0], $unit0[0], 'The sole attribute must be the member NodeAttribute');
                $test->assertSame(
                    array_values($attrs),
                    array_values($unit0),
                    'getUnit(0) on a single-unit SequenceAttribute must equal the entire attributes array',
                );
            },
        );
    }

    // -------------------------------------------------------------------------
    // Scenariusz 2: 2 units
    // -------------------------------------------------------------------------

    /**
     * For a two-member object, unit 0 must start with the first member and include
     * trailing structural attributes (trivia, comma, trivia). Unit 1 must end with
     * the second member and include leading structural attributes.
     *
     * Both units must have at least 3 elements (content + surrounding structure).
     */
    #[Test]
    public function getUnitWithTwoMembersReturnsCorrectSlicesWithStructuralAttributes(): void
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
                $test->assertNotNull($membersAttr, 'Expected a "members" SequenceAttribute');

                $this->attachValidSequence($membersAttr, $objectSequence);

                $test->assertSame(2, $membersAttr->getUnitCount());

                $attrs = $membersAttr->attributes;
                [$pos1, $pos2] = $this->findMemberPositions($attrs);

                $unit0 = $membersAttr->getUnit(0);
                $unit1 = $membersAttr->getUnit(1);

                // Exact slice comparison
                $test->assertSame(
                    array_values(array_slice($attrs, 0, $pos2)),
                    $unit0,
                    'unit0 must be the slice from index 0 up to (not including) the second member',
                );
                $test->assertSame(
                    array_values(array_slice($attrs, $pos1 + 1)),
                    $unit1,
                    'unit1 must be the slice from after the first member to the end',
                );

                // Member position within each unit
                $test->assertSame($attrs[$pos1], $unit0[0], 'member1 must be the FIRST element of unit0');
                $test->assertSame($attrs[$pos2], $unit1[count($unit1) - 1], 'member2 must be the LAST element of unit1');

                // At least 3 elements per unit (content + structural attributes)
                $test->assertGreaterThanOrEqual(3, count($unit0), 'unit0 must have at least 3 elements');
                $test->assertGreaterThanOrEqual(3, count($unit1), 'unit1 must have at least 3 elements');
            },
        );
    }

    // -------------------------------------------------------------------------
    // Scenariusz 3: 3 units — pierwszy i ostatni krótsze od środkowego
    // -------------------------------------------------------------------------

    /**
     * For a three-member object, the middle unit (index 1) must contain both the
     * trailing structure of unit 0 and the leading structure of unit 2, making it
     * longer than the boundary units.
     *
     * Verifies: count(unit0) < count(unit1) and count(unit2) < count(unit1).
     */
    #[Test]
    public function getUnitWithThreeMembersFirstAndLastAreShorterThanMiddle(): void
    {
        $objectSequence = null;

        $this->assertGrammarParsing(
            string: '{"a":1,"b":2,"c":3}',
            grammar: $this->grammar(),
            assertCompiledGrammarValid: function (CompiledGrammar $cg) use (&$objectSequence): void {
                $objectSequence = $this->objectSequence($cg);
            },
            assertParsingResultValid: function (NodeInterface $result, self $test) use (&$objectSequence): void {
                $membersAttr = $this->findMembersGroupedAttr($result);
                $test->assertNotNull($membersAttr, 'Expected a "members" SequenceAttribute');

                $this->attachValidSequence($membersAttr, $objectSequence);

                $test->assertSame(3, $membersAttr->getUnitCount());

                $attrs = $membersAttr->attributes;
                [$pos1, $pos2, $pos3] = $this->findMemberPositions($attrs);

                $unit0 = $membersAttr->getUnit(0);
                $unit1 = $membersAttr->getUnit(1);
                $unit2 = $membersAttr->getUnit(2);

                // Exact slice comparison
                $test->assertSame(
                    array_values(array_slice($attrs, 0, $pos2)),
                    $unit0,
                    'unit0 must be the slice from 0 to just before member2',
                );
                $test->assertSame(
                    array_values(array_slice($attrs, $pos1 + 1, $pos3 - $pos1 - 1)),
                    $unit1,
                    'unit1 must be the slice from after member1 to just before member3',
                );
                $test->assertSame(
                    array_values(array_slice($attrs, $pos2 + 1)),
                    $unit2,
                    'unit2 must be the slice from after member2 to the end',
                );

                // Member position within each unit
                $test->assertSame($attrs[$pos1], $unit0[0], 'member1 must be the FIRST element of unit0');
                $test->assertSame($attrs[$pos2], $unit1[$pos2 - $pos1 - 1], 'member2 must be in the middle of unit1');
                $test->assertSame($attrs[$pos3], $unit2[count($unit2) - 1], 'member3 must be the LAST element of unit2');

                // Size assertions: first and last are shorter than middle
                $test->assertLessThan(
                    count($unit1),
                    count($unit0),
                    'First unit must be shorter than the middle unit',
                );
                $test->assertLessThan(
                    count($unit1),
                    count($unit2),
                    'Last unit must be shorter than the middle unit',
                );
            },
        );
    }

    // -------------------------------------------------------------------------
    // Scenariusz 4: out of bounds
    // -------------------------------------------------------------------------

    #[Test]
    public function getUnitThrowsOutOfRangeExceptionForNegativeIndex(): void
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

                $this->attachValidSequence($membersAttr, $objectSequence);

                $test->expectException(OutOfRangeException::class);
                $membersAttr->getUnit(-1);
            },
        );
    }

    #[Test]
    public function getUnitThrowsOutOfRangeExceptionForIndexEqualToUnitCount(): void
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

                $this->attachValidSequence($membersAttr, $objectSequence);

                $test->expectException(OutOfRangeException::class);
                $membersAttr->getUnit(1); // only 1 unit, index 1 is out of range
            },
        );
    }
}
