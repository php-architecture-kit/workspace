<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Func\Foundation\Grammar\Definition\Rule;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeAttributeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\NodeAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawContentAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeType;
use PhpArchitecture\Parser\Foundation\Shared\Meta\MetaInterface;
use PhpArchitecture\Parser\Tests\Func\Grammar\GrammarTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * `ChoiceAttribute` was intentionally removed. The information it used to carry
 * (which alternative was matched, out of the full set of choices) now lives in
 * `meta['alternatives']` on whichever attribute the matched alternative actually
 * resolves to (NodeAttribute/OptionalAttribute/GroupAttribute/RawContentAttribute/...),
 * via NodeTypeResolver::resolveNodeType() falling back to NodeType::Node when the
 * choice's alternatives don't agree on a single NodeType.
 */
#[Group('func')]
final class ChoiceAlternativesMetaTest extends GrammarTestCase
{
    #[Test]
    public function shouldRawAlternativesProduceRawContentAttributeWithAlternativesInMeta(): void
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
                $resolved = $this->findAttributeWithAlternatives($node, ['null', 'true', 'false']);
                $test->assertNotNull($resolved, "Expected an attribute carrying the choice alternatives in meta['alternatives']");
                $test->assertInstanceOf(RawContentAttribute::class, $resolved);
                $test->assertSame('null', (string) $resolved);
            },
        );
    }

    #[Test]
    public function shouldNodeTypeAlternativesProduceNodeAttributeWithAlternativesInMeta(): void
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
                $resolved = $this->findAttributeWithAlternatives($node, ['null', 'true', 'false']);
                $test->assertNotNull($resolved, "Expected an attribute carrying the choice alternatives in meta['alternatives']");
                $test->assertInstanceOf(NodeAttribute::class, $resolved);
                $test->assertSame('true', (string) $resolved);
            },
        );
    }

    #[Test]
    public function shouldAlternativesMetaListAllOptionsRegardlessOfMatch(): void
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
                $resolved = $this->findAttributeWithAlternatives($node, ['null', 'true', 'false']);
                $test->assertNotNull($resolved, "Expected an attribute carrying the choice alternatives in meta['alternatives']");
                $test->assertSame(['null', 'true', 'false'], $resolved->meta['alternatives']);
                $test->assertSame('false', (string) $resolved);
            },
        );
    }

    /**
     * @param string[] $alternatives
     */
    private function findAttributeWithAlternatives(NodeInterface $node, array $alternatives): ?NodeAttributeInterface
    {
        foreach ($node->getAttributes() as $attr) {
            if ($attr instanceof MetaInterface && ($attr->meta['alternatives'] ?? null) === $alternatives) {
                return $attr;
            }
            if ($attr instanceof NodeAttribute) {
                $found = $this->findAttributeWithAlternatives($attr->node, $alternatives);
                if ($found !== null) {
                    return $found;
                }
            }
        }
        return null;
    }
}
