<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Func\Foundation\Grammar\Definition\Sequence;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Tests\Func\Grammar\GrammarTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('func')]
final class SequenceAlternativeTest extends GrammarTestCase
{
    #[Test]
    public function shouldMatchFirstAlternativeInLine(): void
    {
        $grammar = new Grammar('alt-test');
        $grammar->global->add(Rule::token('a', 'a'));
        $grammar->global->add(Rule::token('b', 'b'));
        $grammar->global->withRootSequence('a|b');

        $this->assertGrammarParsing(
            string: 'a',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $test->assertSame('a', (string) $node);
            },
            requireBofEof: false,
        );
    }

    #[Test]
    public function shouldMatchSecondAlternativeInLine(): void
    {
        $grammar = new Grammar('alt-test');
        $grammar->global->add(Rule::token('a', 'a'));
        $grammar->global->add(Rule::token('b', 'b'));
        $grammar->global->withRootSequence('a|b');

        $this->assertGrammarParsing(
            string: 'b',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $test->assertSame('b', (string) $node);
            },
            requireBofEof: false,
        );
    }

    #[Test]
    public function shouldAlternativeAttributeNameCombinesRuleNames(): void
    {
        // Inline alternatives in a sequence get attribute name 'a|b' (not 'a' or 'b').
        $grammar = new Grammar('alt-test');
        $grammar->global->add(Rule::token('a', 'a'));
        $grammar->global->add(Rule::token('b', 'b'));
        $grammar->global->withRootSequence('a|b');

        $this->assertGrammarParsing(
            string: 'a',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $attributes = $node->getAttributes();

                $test->assertCount(1, $attributes);
                $test->assertSame('a|b', $attributes[0]->getName());
            },
            requireBofEof: false,
        );
    }
}
