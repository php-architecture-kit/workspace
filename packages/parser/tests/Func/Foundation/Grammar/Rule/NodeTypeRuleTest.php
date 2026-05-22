<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Func\Foundation\Grammar\Rule;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\NodeAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\RawContentAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\StructureAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeType;
use PhpArchitecture\Parser\Tests\Func\Grammar\GrammarTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('func')]
final class NodeTypeRuleTest extends GrammarTestCase
{
    // --- NodeType::Raw ---

    #[Test]
    public function shouldProduceRawContentAttributeWhenNodeTypeIsRaw(): void
    {
        $grammar = new Grammar('nodetype-test');
        $grammar->global->add(Rule::token('x', 'x', type: NodeType::Raw));

        $this->assertGrammarParsing(
            string: 'x',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $attributes = $node->getAttributes();

                $test->assertCount(1, $attributes);
                $test->assertInstanceOf(RawContentAttribute::class, $attributes[0]);
            },
        );
    }

    #[Test]
    public function shouldRawContentAttributeContentEqualsMatchedRaw(): void
    {
        $grammar = new Grammar('nodetype-test');
        $grammar->global->add(Rule::token('x', 'x', type: NodeType::Raw));

        $this->assertGrammarParsing(
            string: 'x',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $attribute = $node->getAttributes()[0];

                $test->assertInstanceOf(RawContentAttribute::class, $attribute);
                $test->assertSame('x', $attribute->content);
            },
        );
    }

    // --- NodeType::Structure ---

    #[Test]
    public function shouldProduceStructureAttributeWhenNodeTypeIsStructure(): void
    {
        $grammar = new Grammar('nodetype-test');
        $grammar->global->add(Rule::token('x', 'x', type: NodeType::Structure));

        $this->assertGrammarParsing(
            string: 'x',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $attributes = $node->getAttributes();

                $test->assertCount(1, $attributes);
                $test->assertInstanceOf(StructureAttribute::class, $attributes[0]);
            },
        );
    }

    #[Test]
    public function shouldStructureAttributeContentEqualsMatchedRaw(): void
    {
        $grammar = new Grammar('nodetype-test');
        $grammar->global->add(Rule::token('x', 'x', type: NodeType::Structure));

        $this->assertGrammarParsing(
            string: 'x',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $attribute = $node->getAttributes()[0];

                $test->assertInstanceOf(StructureAttribute::class, $attribute);
                $test->assertSame('x', $attribute->content);
                $test->assertTrue($attribute->present);
            },
        );
    }

    // --- NodeType::Node ---

    #[Test]
    public function shouldProduceNodeAttributeWhenNodeTypeIsNode(): void
    {
        $grammar = new Grammar('nodetype-test');
        $grammar->global->add(Rule::token('x', 'x', type: NodeType::Node));

        $this->assertGrammarParsing(
            string: 'x',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $attributes = $node->getAttributes();

                $test->assertCount(1, $attributes);
                $test->assertInstanceOf(NodeAttribute::class, $attributes[0]);
            },
        );
    }

    #[Test]
    public function shouldNodeAttributeWrapChildNode(): void
    {
        $grammar = new Grammar('nodetype-test');
        $grammar->global->add(Rule::token('x', 'x', type: NodeType::Node));

        $this->assertGrammarParsing(
            string: 'x',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $attribute = $node->getAttributes()[0];

                $test->assertInstanceOf(NodeAttribute::class, $attribute);
                $test->assertInstanceOf(NodeInterface::class, $attribute->node);
                $test->assertSame('x', (string) $attribute);
            },
        );
    }

    // --- NodeType::Skip ---

    #[Test]
    public function shouldProduceNoAttributeWhenNodeTypeIsSkip(): void
    {
        $grammar = new Grammar('nodetype-test');
        $grammar->global->add(Rule::token('x', 'x')->skipInNodeTree());

        $this->assertGrammarParsing(
            string: 'x',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $test->assertCount(0, $node->getAttributes());
            },
        );
    }

    #[Test]
    public function shouldCastNodeToEmptyStringWhenAllTokensSkipped(): void
    {
        $grammar = new Grammar('nodetype-test');
        $grammar->global->add(Rule::token('x', 'x')->skipInNodeTree());

        $this->assertGrammarParsing(
            string: 'x',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $test->assertSame('', (string) $node);
            },
        );
    }
}
