<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Func\Foundation\Grammar\Definition\Sequence;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\GroupedAttribute;
use PhpArchitecture\Parser\Tests\Func\Grammar\GrammarTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('func')]
final class SequenceGroupTagTest extends GrammarTestCase
{
    private function listGrammar(): Grammar
    {
        $grammar = new Grammar('group-test');
        $grammar->global->add(Rule::token('open', '['));
        $grammar->global->add(Rule::token('close', ']'));
        $grammar->global->add(Rule::token('comma', ','));
        $grammar->global->add(Rule::token('item', 'x'));
        $grammar->global->withRootSequence('open ?(item (comma item)*)/g close');

        return $grammar;
    }

    #[Test]
    public function shouldPreserveStringForEmptyList(): void
    {
        $this->assertGrammarParsing(
            string: '[]',
            grammar: $this->listGrammar(),
            requireBofEof: false,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $test->assertSame('[]', (string) $node);
            },
        );
    }

    #[Test]
    public function shouldPreserveStringForSingleItem(): void
    {
        $this->assertGrammarParsing(
            string: '[x]',
            grammar: $this->listGrammar(),
            requireBofEof: false,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $test->assertSame('[x]', (string) $node);
            },
        );
    }

    #[Test]
    public function shouldPreserveStringForMultipleItems(): void
    {
        $this->assertGrammarParsing(
            string: '[x,x,x]',
            grammar: $this->listGrammar(),
            requireBofEof: false,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $test->assertSame('[x,x,x]', (string) $node);
            },
        );
    }

    #[Test]
    public function shouldProduceNoGroupedAttributeForEmptyList(): void
    {
        $this->assertGrammarParsing(
            string: '[]',
            grammar: $this->listGrammar(),
            requireBofEof: false,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $attributes = $node->getAttributes();

                foreach ($attributes as $attr) {
                    $test->assertNotInstanceOf(GroupedAttribute::class, $attr);
                }
            },
        );
    }

    #[Test]
    public function shouldProduceSingleGroupedAttributeWithOneNodeForSingleItem(): void
    {
        $this->assertGrammarParsing(
            string: '[x]',
            grammar: $this->listGrammar(),
            requireBofEof: false,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $groupAttrs = array_filter(
                    $node->getAttributes(),
                    static fn($a) => $a instanceof GroupedAttribute && $a->name === 'item',
                );

                $test->assertCount(1, $groupAttrs);
                $groupAttr = array_values($groupAttrs)[0];
                $test->assertCount(1, $groupAttr->attributes);
                $test->assertSame('x', (string) $groupAttr->attributes[0]);
            },
        );
    }

    #[Test]
    public function shouldProduceSingleGroupedAttributeWithFiveNodesForThreeItems(): void
    {
        // Grammar: ?(item (comma item)*)/g → for [x,x,x]: item, comma, item, comma, item = 5 nodes
        $this->assertGrammarParsing(
            string: '[x,x,x]',
            grammar: $this->listGrammar(),
            requireBofEof: false,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $groupAttrs = array_filter(
                    $node->getAttributes(),
                    static fn($a) => $a instanceof GroupedAttribute && $a->name === 'item',
                );

                $test->assertCount(1, $groupAttrs);
                $groupAttr = array_values($groupAttrs)[0];
                $test->assertCount(5, $groupAttr->attributes);
            },
        );
    }

    #[Test]
    public function shouldCollectItemsAndSeparatorsInOrderIntoGroupedAttribute(): void
    {
        $this->assertGrammarParsing(
            string: '[x,x,x]',
            grammar: $this->listGrammar(),
            requireBofEof: false,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $groupAttr = array_values(array_filter(
                    $node->getAttributes(),
                    static fn($a) => $a instanceof GroupedAttribute && $a->name === 'item',
                ))[0];

                $test->assertSame('x,x,x', (string) $groupAttr);
            },
        );
    }

    #[Test]
    public function shouldProduceGroupedAttributeEvenForSingleItemSoTreeSchemaKnowsItIsList(): void
    {
        // This is the key test: a single item must STILL produce GroupedAttribute (not NodeAttribute),
        // so TreeSchema can distinguish list vs single-node semantics.
        $this->assertGrammarParsing(
            string: '[x]',
            grammar: $this->listGrammar(),
            requireBofEof: false,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $itemAttrs = array_filter(
                    $node->getAttributes(),
                    static fn($a) => $a instanceof GroupedAttribute && $a->name === 'item',
                );

                $test->assertNotEmpty($itemAttrs, 'Single item must produce GroupedAttribute, not NodeAttribute');
            },
        );
    }
}
