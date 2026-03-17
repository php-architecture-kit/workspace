<?php

declare(strict_types=1);

namespace Tests\PhpArchitecture\Parser\Unit\Model\Grammar\Rules;

use InvalidArgumentException;
use PhpArchitecture\Parser\Model\Grammar\Rules\NestedSequence;
use PhpArchitecture\Parser\Model\Grammar\Rules\SequenceNode;
use PhpArchitecture\Parser\Model\Grammar\Rules\SequenceRule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SequenceRuleTest extends TestCase
{
    #[Test]
    #[DataProvider('provideValidSequenceRulesFromFile')]
    public function fromStringCreatesValidInstanceForAllValidSequenceRules(string $sequence): void
    {
        $rule = SequenceRule::fromString($sequence);
        $this->assertInstanceOf(SequenceRule::class, $rule);
        $this->assertSame($sequence, $rule->toString());
    }

    #[Test]
    public function singleNodeSequence(): void
    {
        $rule = SequenceRule::fromString('token');
        $this->assertCount(1, $rule->nodes);
        $this->assertInstanceOf(SequenceNode::class, $rule->nodes[0]);
    }

    #[Test]
    public function multipleNodesSequence(): void
    {
        $rule = SequenceRule::fromString('token member value');
        $this->assertCount(3, $rule->nodes);
        $this->assertInstanceOf(SequenceNode::class, $rule->nodes[0]);
        $this->assertInstanceOf(SequenceNode::class, $rule->nodes[1]);
        $this->assertInstanceOf(SequenceNode::class, $rule->nodes[2]);
    }

    #[Test]
    public function sequenceWithNestedSequence(): void
    {
        $rule = SequenceRule::fromString('token (ws member)');
        $this->assertCount(2, $rule->nodes);
        $this->assertInstanceOf(SequenceNode::class, $rule->nodes[0]);
        $this->assertInstanceOf(NestedSequence::class, $rule->nodes[1]);
    }

    #[Test]
    public function sequenceWithOptionalNode(): void
    {
        $rule = SequenceRule::fromString('token ?member');
        $this->assertCount(2, $rule->nodes);
    }

    #[Test]
    public function sequenceWithQuantifiers(): void
    {
        $rule = SequenceRule::fromString('token* member+ value');
        $this->assertCount(3, $rule->nodes);
    }

    #[Test]
    public function sequenceWithAnchorsAndTags(): void
    {
        $rule = SequenceRule::fromString('token[t]/s member[m]/t');
        $this->assertCount(2, $rule->nodes);
        
        $firstNode = $rule->nodes[0];
        $this->assertInstanceOf(SequenceNode::class, $firstNode);
        $this->assertSame('t', $firstNode->anchorName);
        $this->assertSame(['s'], $firstNode->tags);
    }

    #[Test]
    public function sequenceWithLookaheadAtEnd(): void
    {
        $rule = SequenceRule::fromString('token >member');
        $this->assertCount(2, $rule->nodes);
        $this->assertTrue($rule->nodes[1]->isLookahead);
    }

    #[Test]
    public function sequenceWithLookbehindAtStart(): void
    {
        $rule = SequenceRule::fromString('<token member');
        $this->assertCount(2, $rule->nodes);
        $this->assertTrue($rule->nodes[0]->isLookbehind);
    }

    #[Test]
    public function sequenceWithBothLookaheadAndLookbehind(): void
    {
        $rule = SequenceRule::fromString('<token member >value');
        $this->assertCount(3, $rule->nodes);
        $this->assertTrue($rule->nodes[0]->isLookbehind);
        $this->assertTrue($rule->nodes[2]->isLookahead);
    }

    #[Test]
    public function getAllNodeNamesReturnsAllUniqueNames(): void
    {
        $rule = SequenceRule::fromString('token member token value');
        $names = $rule->getAllNodeNames();
        
        $this->assertCount(3, $names);
        $this->assertContains('token', $names);
        $this->assertContains('member', $names);
        $this->assertContains('value', $names);
    }

    #[Test]
    public function getAllNodeNamesIncludesNestedSequenceNodes(): void
    {
        $rule = SequenceRule::fromString('token (ws member) value');
        $names = $rule->getAllNodeNames();
        
        $this->assertContains('token', $names);
        $this->assertContains('ws', $names);
        $this->assertContains('member', $names);
        $this->assertContains('value', $names);
    }

    #[Test]
    public function getFirstValidNodeNodeNamesReturnsFirstNonLookbehind(): void
    {
        $rule = SequenceRule::fromString('token member');
        $names = $rule->getFirstValidNodeNodeNames();
        
        $this->assertSame(['token'], $names);
    }

    #[Test]
    public function getFirstValidNodeNodeNamesSkipsLookbehind(): void
    {
        $rule = SequenceRule::fromString('<token member');
        $names = $rule->getFirstValidNodeNodeNames();
        
        $this->assertSame(['member'], $names);
    }

    #[Test]
    public function getFirstValidNodeNodeNamesIncludesOptionalNodes(): void
    {
        $rule = SequenceRule::fromString('?token member');
        $names = $rule->getFirstValidNodeNodeNames();
        
        $this->assertContains('token', $names);
        $this->assertContains('member', $names);
    }

    #[Test]
    public function toStringReconstructsOriginalSequence(): void
    {
        $sequences = [
            'token',
            'token member',
            'token ?member',
            'token member*',
            'token (ws member)',
            'token[t]/s member[m]/t',
            '<token member',
            'token >member',
            'token (ws member)/s value',
        ];

        foreach ($sequences as $sequence) {
            $rule = SequenceRule::fromString($sequence);
            $this->assertSame($sequence, $rule->toString());
        }
    }

    #[Test]
    public function throwsExceptionForEmptySequence(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Sequence can't be empty");
        SequenceRule::fromString('');
    }

    #[Test]
    public function throwsExceptionForForbiddenSubstrings(): void
    {
        $forbiddenCases = [
            'token+|member',
            'token*|member',
            'token|?member',
            'token|<member',
            'token|>member',
            'token||member',
        ];

        foreach ($forbiddenCases as $case) {
            try {
                SequenceRule::fromString($case);
                $this->fail("Expected exception for: $case");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('forbidden substrings', $e->getMessage());
            }
        }
    }

    #[Test]
    public function throwsExceptionForLookaheadInMiddle(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Lookahead is not allowed in the middle of a sequence');
        SequenceRule::fromString('token >member value');
    }

    #[Test]
    public function throwsExceptionForLookbehindInMiddle(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Lookbehind is not allowed in the middle of a sequence');
        SequenceRule::fromString('token <member value');
    }

    #[Test]
    public function throwsExceptionForSequenceWithMinTokensZero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('minimum members count equal to 0');
        SequenceRule::fromString('?token ?member');
    }

    #[Test]
    public function assertSequenceMatchAnchorRequirementsValidatesCorrectly(): void
    {
        $original = SequenceRule::fromString('token member');
        $anchor = SequenceRule::fromString('t m');
        
        $this->expectNotToPerformAssertions();
        $anchor->assertSequenceMatchAnchorRequirements($original);
    }

    #[Test]
    public function assertSequenceMatchAnchorRequirementsThrowsForMismatch(): void
    {
        $original = SequenceRule::fromString('token member');
        $anchor = SequenceRule::fromString('(t m)');
        
        $this->expectException(InvalidArgumentException::class);
        $anchor->assertSequenceMatchAnchorRequirements($original);
    }

    public static function provideValidSequenceRulesFromFile(): array
    {
        $filePath = __DIR__ . '/../../../../valid_sequences.txt';
        $content = file_get_contents($filePath);
        $lines = array_filter(
            array_map('trim', explode("\n", $content)),
            fn($line) => $line !== '' && str_contains($line, ' ')
        );

        $data = [];
        foreach ($lines as $line) {
            $data[$line] = [$line];
        }
        return $data;
    }
}
