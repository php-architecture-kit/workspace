<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Unit\Foundation\Parsing\Model\Attribute;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PhpArchitecture\Parser\Foundation\Matching\Model\NestedSequence;
use PhpArchitecture\Parser\Foundation\Matching\Model\SequenceNode;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\SequenceValidityCursor;

#[Group('unit')]
final class SequenceValidityCursorTest extends TestCase
{
    // Helper builders to keep tests readable

    private static function node(string|array $alternatives, int $min = 1, int $max = 1): SequenceNode
    {
        return new SequenceNode(
            alternatives: (array) $alternatives,
            min: $min,
            max: $max,
        );
    }

    private static function optional(string|array $alternatives): SequenceNode
    {
        return self::node($alternatives, min: 0, max: PHP_INT_MAX);
    }

    private static function seq(array $alternative, int $min = 1, int $max = 1): NestedSequence
    {
        return new NestedSequence(
            alternativeSequences: [$alternative],
            min: $min,
            max: $max,
        );
    }

    // -------------------------------------------------------------------------
    // Linear sequence: A B C
    // -------------------------------------------------------------------------

    #[Test]
    public function linearSequence_freshCursor_firstNodeIsValid(): void
    {
        $cursor = new SequenceValidityCursor(self::seq([
            self::node('A'),
            self::node('B'),
            self::node('C'),
        ]));

        self::assertSame(['A'], $cursor->getValidNextNames());
        self::assertFalse($cursor->canComplete());
    }

    #[Test]
    public function linearSequence_advancesStepByStep(): void
    {
        $cursor = new SequenceValidityCursor(self::seq([
            self::node('A'),
            self::node('B'),
            self::node('C'),
        ]));

        $cursor->advance('A');
        self::assertSame(['B'], $cursor->getValidNextNames());
        self::assertFalse($cursor->canComplete());

        $cursor->advance('B');
        self::assertSame(['C'], $cursor->getValidNextNames());
        self::assertFalse($cursor->canComplete());

        $cursor->advance('C');
        self::assertSame([], $cursor->getValidNextNames());
        self::assertTrue($cursor->canComplete());
    }

    #[Test]
    public function linearSequence_wrongNameThrows(): void
    {
        $cursor = new SequenceValidityCursor(self::seq([
            self::node('A'),
            self::node('B'),
        ]));

        $this->expectException(InvalidArgumentException::class);
        $cursor->advance('B');
    }

    // -------------------------------------------------------------------------
    // Optional sequence: ?A (min=0 outer)
    // -------------------------------------------------------------------------

    #[Test]
    public function optionalSequence_freshCursor_canCompleteImmediately(): void
    {
        $cursor = new SequenceValidityCursor(self::seq(
            [self::node('A'), self::node('B')],
            min: 0,
            max: 1,
        ));

        self::assertTrue($cursor->canComplete());
        self::assertSame(['A'], $cursor->getValidNextNames());
    }

    // -------------------------------------------------------------------------
    // Optional skip: -* comma (optional whitespace before mandatory comma)
    // -------------------------------------------------------------------------

    #[Test]
    public function optionalSkip_mandatoryNodeReachableWithoutAddingOptional(): void
    {
        // -* comma  →  can advance directly to 'comma', skipping '-*'
        $cursor = new SequenceValidityCursor(self::seq([
            self::optional('-'),
            self::node('comma'),
        ]));

        self::assertSame(['-', 'comma'], $cursor->getValidNextNames());

        // advance directly to mandatory 'comma' without adding whitespace
        $cursor->advance('comma');
        self::assertSame([], $cursor->getValidNextNames());
        self::assertTrue($cursor->canComplete());
    }

    // -------------------------------------------------------------------------
    // Union alternatives: (A B) | (C D)
    // -------------------------------------------------------------------------

    #[Test]
    public function unionAlternatives_freshCursor_bothFirstNodesAreValid(): void
    {
        $cursor = new SequenceValidityCursor(new NestedSequence(
            alternativeSequences: [
                [self::node('A'), self::node('B')],
                [self::node('C'), self::node('D')],
            ],
            min: 1,
            max: 1,
        ));

        self::assertEqualsCanonicalizing(['A', 'C'], $cursor->getValidNextNames());
    }

    #[Test]
    public function unionAlternatives_commitsToChosenAlternative(): void
    {
        $cursor = new SequenceValidityCursor(new NestedSequence(
            alternativeSequences: [
                [self::node('A'), self::node('B')],
                [self::node('C'), self::node('D')],
            ],
            min: 1,
            max: 1,
        ));

        $cursor->advance('C');

        // After choosing C, only D is valid — not B (which belongs to the other alternative)
        self::assertSame(['D'], $cursor->getValidNextNames());
    }

    // -------------------------------------------------------------------------
    // Doubly-nested: member (-* comma -* member)*
    // This is the core JSON members use case.
    // -------------------------------------------------------------------------

    private function buildMembersSequence(): NestedSequence
    {
        $ws = self::optional('-');

        $innerRepetition = new NestedSequence(
            alternativeSequences: [[
                clone $ws,
                self::node('comma'),
                clone $ws,
                self::node('member'),
            ]],
            min: 0,
            max: PHP_INT_MAX,
        );

        // ?(member (-* comma -* member)*)  — outer is ZeroOrOne
        return new NestedSequence(
            alternativeSequences: [[
                self::node('member'),
                $innerRepetition,
            ]],
            min: 0,
            max: 1,
        );
    }

    #[Test]
    public function members_freshCursor_firstMemberIsValidAndSequenceIsCompletable(): void
    {
        $cursor = new SequenceValidityCursor($this->buildMembersSequence());

        self::assertSame(['member'], $cursor->getValidNextNames());
        self::assertTrue($cursor->canComplete()); // outer is optional
    }

    #[Test]
    public function members_afterFirstMember_commaOrEndAreValid(): void
    {
        $cursor = new SequenceValidityCursor($this->buildMembersSequence());
        $cursor->advance('member');

        self::assertEqualsCanonicalizing(['-', 'comma'], $cursor->getValidNextNames());
        self::assertTrue($cursor->canComplete()); // inner repetition is optional
    }

    #[Test]
    public function members_afterComma_memberIsRequired(): void
    {
        $cursor = new SequenceValidityCursor($this->buildMembersSequence());
        $cursor->advance('member');
        $cursor->advance('comma');

        self::assertEqualsCanonicalizing(['-', 'member'], $cursor->getValidNextNames());
        self::assertFalse($cursor->canComplete()); // member is mandatory after comma
    }

    #[Test]
    public function members_afterInnerMember_canRepeatOrEnd(): void
    {
        $cursor = new SequenceValidityCursor($this->buildMembersSequence());
        $cursor->advance('member');
        $cursor->advance('comma');
        $cursor->advance('member');

        // The inner sequence completed one iteration: next can be comma (repeat) or end
        self::assertEqualsCanonicalizing(['-', 'comma'], $cursor->getValidNextNames());
        self::assertTrue($cursor->canComplete());
    }

    #[Test]
    public function members_multipleRepetitions_cursorTracksCorrectly(): void
    {
        $cursor = new SequenceValidityCursor($this->buildMembersSequence());
        $cursor->advance('member');
        $cursor->advance('comma');
        $cursor->advance('member');
        $cursor->advance('comma');
        $cursor->advance('member');

        // Still can repeat or end after third member
        self::assertEqualsCanonicalizing(['-', 'comma'], $cursor->getValidNextNames());
        self::assertTrue($cursor->canComplete());
    }

    #[Test]
    public function members_invalidNameAtStartThrows(): void
    {
        $cursor = new SequenceValidityCursor($this->buildMembersSequence());

        $this->expectException(InvalidArgumentException::class);
        $cursor->advance('comma'); // comma before first member is invalid
    }

    #[Test]
    public function members_invalidNameAfterCommaThrows(): void
    {
        $cursor = new SequenceValidityCursor($this->buildMembersSequence());
        $cursor->advance('member');
        $cursor->advance('comma');

        $this->expectException(InvalidArgumentException::class);
        $cursor->advance('comma'); // comma after comma (no member in between) is invalid
    }

    #[Test]
    public function members_whitespaceCanBeAddedBeforeComma(): void
    {
        $cursor = new SequenceValidityCursor($this->buildMembersSequence());
        $cursor->advance('member');
        $cursor->advance('-'); // whitespace before comma
        $cursor->advance('comma');
        $cursor->advance('-'); // whitespace before member
        $cursor->advance('member');

        self::assertTrue($cursor->canComplete());
    }
}
