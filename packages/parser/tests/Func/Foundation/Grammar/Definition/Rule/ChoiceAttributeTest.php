<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Func\Foundation\Grammar\Definition\Rule;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\ChoiceAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\NodeAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\RawContentAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeType;
use PhpArchitecture\Parser\Tests\Func\Grammar\GrammarTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('func')]
final class ChoiceAttributeTest extends GrammarTestCase
{
    #[Test]
    public function shouldProduceChoiceAttributeForTokenAlternatives(): void
    {
        $grammar = new Grammar('choice-attr-test');
        $grammar->global->add(Rule::keyword('null', caseSensitive: true));
        $grammar->global->add(Rule::keyword('true', caseSensitive: true));
        $grammar->global->add(Rule::keyword('false', caseSensitive: true));
        $grammar->global->add(Rule::choice('value', ['null', 'true', 'false']));
        $grammar->global->withRootSequence('value');

        $this->assertGrammarParsing(
            string: 'null',
            grammar: $grammar,
            requireBofEof: false,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $attrs = $node->getAttributes();

                $test->assertCount(1, $attrs);

                $choice = $attrs[0];
                $test->assertInstanceOf(ChoiceAttribute::class, $choice);
                $test->assertSame('value', $choice->name);
                $test->assertSame(['null', 'true', 'false'], $choice->choices);
                $test->assertInstanceOf(RawContentAttribute::class, $choice->selected);
                $test->assertSame('null', $choice->selected->content);
                $test->assertSame('null', (string) $choice);
            },
        );
    }

    #[Test]
    public function shouldProduceNodeAttributeAsSelectedForNodeTypeAlternatives(): void
    {
        $grammar = new Grammar('choice-attr-node-test');
        $grammar->global->add(Rule::keyword('null', caseSensitive: true, type: NodeType::Node));
        $grammar->global->add(Rule::keyword('true', caseSensitive: true, type: NodeType::Node));
        $grammar->global->add(Rule::keyword('false', caseSensitive: true, type: NodeType::Node));
        $grammar->global->add(Rule::choice('value', ['null', 'true', 'false']));
        $grammar->global->withRootSequence('value');

        $this->assertGrammarParsing(
            string: 'true',
            grammar: $grammar,
            requireBofEof: false,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $choice = $node->getAttributes()[0];

                $test->assertInstanceOf(ChoiceAttribute::class, $choice);
                $test->assertSame('value', $choice->name);
                $test->assertSame(['null', 'true', 'false'], $choice->choices);
                $test->assertInstanceOf(NodeAttribute::class, $choice->selected);
                $test->assertSame('true', $choice->selected->name);
                $test->assertSame('true', (string) $choice);
            },
        );
    }

    #[Test]
    public function shouldChoicesContainAllAlternativesRegardlessOfMatch(): void
    {
        $grammar = new Grammar('choice-attr-all-test');
        $grammar->global->add(Rule::keyword('null', caseSensitive: true));
        $grammar->global->add(Rule::keyword('true', caseSensitive: true));
        $grammar->global->add(Rule::keyword('false', caseSensitive: true));
        $grammar->global->add(Rule::choice('value', ['null', 'true', 'false']));
        $grammar->global->withRootSequence('value');

        $this->assertGrammarParsing(
            string: 'false',
            grammar: $grammar,
            requireBofEof: false,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $choice = $node->getAttributes()[0];

                $test->assertInstanceOf(ChoiceAttribute::class, $choice);
                $test->assertCount(3, $choice->choices);
                $test->assertSame(['null', 'true', 'false'], $choice->choices);
                $test->assertSame('false', (string) $choice);
            },
        );
    }
}
