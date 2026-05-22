<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Func\Foundation\Grammar\Rule;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Region;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Tests\Func\Grammar\GrammarTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('func')]
final class InheritanceTest extends GrammarTestCase
{
    #[Test]
    public function shouldInheritRulesFromGlobal(): void
    {
        // 'open' and 'close' are defined only in global.
        // 'child' region has no own rules but inherits from global.
        // When 'child' is the root, it can tokenize and parse using inherited rules.
        $grammar = new Grammar('inheritance-test');
        $grammar->global->add(Rule::token('open', '['));
        $grammar->global->add(Rule::token('close', ']'));

        $child = new Region('child');
        $child->enableInheritanceFromGlobal(Region::RULES);
        $child->withRootSequence('open close');
        $grammar->global->add($child);
        $grammar->setRootRegion($child);

        $this->assertGrammarParsing(
            string: '[]',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $test->assertSame('[]', (string) $node);
            },
            requireBofEof: false,
        );
    }

    #[Test]
    public function shouldInheritRegionsFromGlobal(): void
    {
        // Sub-region 'inner' is defined in global.
        // 'child' region inherits REGIONS from global, so 'inner' region is available
        // inside 'child' during tokenization.
        $grammar = new Grammar('inheritance-test');
        $grammar->global->add(Rule::token('x', 'x'));

        $inner = (new Region('inner'))
            ->openWith(Rule::token('open', '['), includeOpenRuleMatch: true)
            ->closeWith(Rule::token('close', ']'), includeCloseRuleMatch: true);
        $inner->add(Rule::expr('content', '[a-z]+'));

        $grammar->global->add($inner);

        $child = new Region('child');
        $child->enableInheritanceFromGlobal(Region::RULES | Region::REGIONS);
        $grammar->global->add($child);
        $grammar->setRootRegion($child);

        $this->assertGrammarParsing(
            string: '[abc]x',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $test->assertSame('[abc]x', (string) $node);
            },
            requireBofEof: false,
        );
    }

    #[Test]
    public function shouldInheritRulesFromAncestor(): void
    {
        // 'open' is defined in 'parent' region.
        // 'child' region inherits RULES from ancestor (= direct parent = 'parent').
        // 'child' can use 'open' even though it's not in global.
        $grammar = new Grammar('inheritance-test');

        $parent = new Region('parent');
        $parent->add(Rule::token('open', '['));

        $child = new Region('child');
        $child->enableInheritanceFromAncestor(Region::RULES);
        $child->add(Rule::token('close', ']'));
        $child->withRootSequence('open close');

        $parent->add($child);
        $grammar->global->add($parent);
        $grammar->setRootRegion($child);

        $this->assertGrammarParsing(
            string: '[]',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $test->assertSame('[]', (string) $node);
            },
            requireBofEof: false,
        );
    }
}
