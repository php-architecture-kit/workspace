<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Func\Foundation\Grammar\Definition\Sequence;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\OptionalAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\RawContentAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeType;
use PhpArchitecture\Parser\Tests\Func\Grammar\GrammarTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('func')]
final class SequenceAnchorTest extends GrammarTestCase
{
    #[Test]
    public function shouldUseRuleNameWhenNoAnchor(): void
    {
        $grammar = new Grammar('anchor-test');
        $grammar->global->add(Rule::token('open', '['));
        $grammar->global->add(Rule::token('close', ']'));
        $grammar->global->withRootSequence('open close');

        $this->assertGrammarParsing(
            string: '[]',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $attributes = $node->getAttributes();

                $test->assertCount(2, $attributes);
                $test->assertSame('open', $attributes[0]->getName());
                $test->assertSame('close', $attributes[1]->getName());
            },
            requireBofEof: false,
        );
    }

    #[Test]
    public function shouldUseAnchorNameAsAttributeName(): void
    {
        $grammar = new Grammar('anchor-test');
        $grammar->global->add(Rule::token('open', '['));
        $grammar->global->add(Rule::token('close', ']'));
        $grammar->global->withRootSequence('open[opener] close[closer]');

        $this->assertGrammarParsing(
            string: '[]',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $attributes = $node->getAttributes();

                $test->assertCount(2, $attributes);
                $test->assertInstanceOf(RawContentAttribute::class, $attributes[0]);
                $test->assertInstanceOf(RawContentAttribute::class, $attributes[1]);
                $test->assertSame('opener', $attributes[0]->getName());
                $test->assertSame('closer', $attributes[1]->getName());
            },
            requireBofEof: false,
        );
    }

    #[Test]
    public function shouldAnchorNameDifferFromRuleName(): void
    {
        $grammar = new Grammar('anchor-test');
        $grammar->global->add(Rule::token('open', '['));
        $grammar->global->add(Rule::token('close', ']'));
        $grammar->global->withRootSequence('open[opener] close[closer]');

        $this->assertGrammarParsing(
            string: '[]',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $attributes = $node->getAttributes();

                $test->assertNotSame('open', $attributes[0]->getName());
                $test->assertNotSame('close', $attributes[1]->getName());
            },
            requireBofEof: false,
        );
    }

    #[Test]
    public function shouldSupportAnchorOnOptionalNode(): void
    {
        // NodeType::Node is required to get OptionalAttribute — without it, Raw produces RawContentAttribute
        $grammar = new Grammar('anchor-test');
        $grammar->global->add(Rule::token('open', '[', type: NodeType::Node));
        $grammar->global->add(Rule::token('close', ']'));
        $grammar->global->withRootSequence('?open[bracket] close');

        $this->assertGrammarParsing(
            string: ']',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $attributes = $node->getAttributes();

                $test->assertCount(2, $attributes);
                $test->assertInstanceOf(OptionalAttribute::class, $attributes[0]);
                $test->assertSame('bracket', $attributes[0]->getName());
                $test->assertNull($attributes[0]->node);
            },
            requireBofEof: false,
        );
    }
}
