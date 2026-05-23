<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Func\Foundation\Grammar\Definition\Region;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Region;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\NodeAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\RawRegionAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeType;
use PhpArchitecture\Parser\Tests\Func\Grammar\GrammarTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('func')]
final class RegionNodeTypeTest extends GrammarTestCase
{
    #[Test]
    public function shouldDefaultRegionProduceNodeAttribute(): void
    {
        // Default region NodeType is Node — the resulting parsed attribute is NodeAttribute.
        $grammar = new Grammar('region-node-type-test');

        $inner = (new Region('inner'))
            ->openWith(Rule::token('open', '['), includeOpenRuleMatch: true)
            ->closeWith(Rule::token('close', ']'), includeCloseRuleMatch: true);
        $inner->add(Rule::expr('content', '[a-z]+'));

        $grammar->global->add($inner);
        $grammar->global->withRootSequence('inner');

        $this->assertGrammarParsing(
            string: '[abc]',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $attributes = $node->getAttributes();

                $test->assertCount(1, $attributes);
                $test->assertInstanceOf(NodeAttribute::class, $attributes[0]);
                $test->assertSame('inner', $attributes[0]->getName());
            },
            requireBofEof: false,
        );
    }

    #[Test]
    public function shouldRegionWithRawNodeTypeProduceRawRegionAttribute(): void
    {
        // setNodeType(NodeType::Raw) on a Region causes its parsed attribute to be RawRegionAttribute.
        $grammar = new Grammar('region-node-type-test');

        $inner = (new Region('inner'))
            ->setNodeType(NodeType::Raw)
            ->openWith(Rule::token('open', '['), includeOpenRuleMatch: true)
            ->closeWith(Rule::token('close', ']'), includeCloseRuleMatch: true);
        $inner->add(Rule::expr('content', '[a-z]+'));

        $grammar->global->add($inner);
        $grammar->global->withRootSequence('inner');

        $this->assertGrammarParsing(
            string: '[abc]',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $attributes = $node->getAttributes();

                $test->assertCount(1, $attributes);
                $test->assertInstanceOf(RawRegionAttribute::class, $attributes[0]);
                $test->assertSame('inner', $attributes[0]->getName());
            },
            requireBofEof: false,
        );
    }
}
