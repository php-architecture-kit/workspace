<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Func\Foundation\Grammar\Definition\Rule;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeAttributeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\NodeAttribute;
use PhpArchitecture\Parser\Foundation\Shared\Meta\MetaInterface;
use PhpArchitecture\Parser\Tests\Func\Grammar\GrammarTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('func')]
final class ChoiceRuleTest extends GrammarTestCase
{
    // --- Tokenization and matching ---

    #[Test]
    public function shouldMatchFirstAlternative(): void
    {
        $grammar = $this->buildGrammar();

        $this->assertGrammarParsing(
            string: 'null',
            grammar: $grammar,
            requireBofEof: false,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $test->assertSame('null', (string) $node);
            },
        );
    }

    #[Test]
    public function shouldMatchMiddleAlternative(): void
    {
        $grammar = $this->buildGrammar();

        $this->assertGrammarParsing(
            string: 'true',
            grammar: $grammar,
            requireBofEof: false,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $test->assertSame('true', (string) $node);
            },
        );
    }

    #[Test]
    public function shouldMatchLastAlternative(): void
    {
        $grammar = $this->buildGrammar();

        $this->assertGrammarParsing(
            string: 'false',
            grammar: $grammar,
            requireBofEof: false,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $test->assertSame('false', (string) $node);
            },
        );
    }

    // --- Parsing result (NodeInterface) ---

    /**
     * `ChoiceAttribute` was removed — the matched alternative's identity now lives
     * in `meta['alternatives']` on whichever attribute it resolves to.
     */
    #[Test]
    public function shouldProduceAttributeWithAlternativesInMetaForMatchedChoice(): void
    {
        $grammar = $this->buildGrammar();

        $this->assertGrammarParsing(
            string: 'null',
            grammar: $grammar,
            requireBofEof: false,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $attributes = $node->getAttributes();

                $test->assertCount(1, $attributes);
                $test->assertInstanceOf(NodeAttribute::class, $attributes[0]);
                $test->assertSame('value', $attributes[0]->getName());

                $resolved = $this->findAttributeWithAlternatives($node, ['null', 'true', 'false']);
                $test->assertNotNull($resolved, "Expected an attribute carrying the choice alternatives in meta['alternatives']");
                $test->assertSame('null', (string) $resolved);
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

    #[Test]
    public function shouldNodeAttributeCastToMatchedAlternative(): void
    {
        $grammar = $this->buildGrammar();

        $this->assertGrammarParsing(
            string: 'true',
            grammar: $grammar,
            requireBofEof: false,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $test->assertSame('true', (string) $node->getAttributes()[0]);
            },
        );
    }

    // --- Inline alternatives in sequence ---

    #[Test]
    public function shouldMatchInlineAlternativeInSequence(): void
    {
        // Alternatives can also be expressed inline in a sequence: 'null|true|false'
        $grammar = new Grammar('choice-test');
        $grammar->global->add(Rule::keyword('null', caseSensitive: true));
        $grammar->global->add(Rule::keyword('true', caseSensitive: true));
        $grammar->global->add(Rule::keyword('false', caseSensitive: true));
        $grammar->global->withRootSequence('null|true|false');

        $this->assertGrammarParsing(
            string: 'true',
            grammar: $grammar,
            requireBofEof: false,
            assertParsingResultValid: function (NodeInterface $node, self $test): void {
                $test->assertSame('true', (string) $node);
            },
        );
    }

    // --- Helpers ---

    private function buildGrammar(): Grammar
    {
        $grammar = new Grammar('choice-test');
        $grammar->global->add(Rule::keyword('null', caseSensitive: true));
        $grammar->global->add(Rule::keyword('true', caseSensitive: true));
        $grammar->global->add(Rule::keyword('false', caseSensitive: true));
        $grammar->global->add(Rule::choice('value', ['null', 'true', 'false']));
        $grammar->global->withRootSequence('value');

        return $grammar;
    }
}
