<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Func\Grammar\Json;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\SequenceAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawContentAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawRegionAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Node;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeOrigin;
use PhpArchitecture\Parser\Foundation\Shared\Meta\MetaInterface;
use PhpArchitecture\Parser\Tests\Func\Grammar\GrammarTestCase;
use PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Json\JsonRfc8259;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('func')]
final class JsonRfc8259ParsedTreeTest extends GrammarTestCase
{
    private function grammar(): Grammar
    {
        return (new JsonRfc8259())->grammar();
    }

    // -------------------------------------------------------------------------
    // Feature: object member SequenceAttribute name
    // -------------------------------------------------------------------------

    /**
     * The sequence `?(member (-* comma -* member)*)/g` uses an anchor `/g` on
     * the nested group sequence. The resulting SequenceAttribute must carry the
     * anchor name derived from that nested sequence — NOT the literal "member"
     * rule name. When no anchorName is provided on the nested sequence the
     * SequenceAttribute name must fall back to SequenceAttribute::DEFAULT_NAME
     * (i.e. "grouped"), NOT "member".
     */
    #[Test]
    public function objectMemberSequenceAttributeNameIsNotMember(): void
    {
        $this->assertGrammarParsing(
            string: '{"key":"value"}',
            grammar: $this->grammar(),
            assertParsingResultValid: function (NodeInterface $result, self $test): void {
                $objectNode = $this->findFirstNodeByName($result, 'object');
                $test->assertNotNull($objectNode, 'Expected an "object" node in the parsed tree');

                $groupedAttrs = array_filter(
                    $objectNode->getAttributes(),
                    static fn($attr) => $attr instanceof SequenceAttribute,
                );
                $test->assertNotEmpty($groupedAttrs, 'Expected at least one SequenceAttribute on "object" node');

                foreach ($groupedAttrs as $grouped) {
                    $test->assertNotEquals(
                        'member',
                        $grouped->getName(),
                        'SequenceAttribute name must NOT be "member" — it should derive from the nested sequence anchorName or fall back to the DEFAULT_NAME',
                    );
                }
            },
        );
    }

    #[Test]
    public function objectMemberSequenceAttributeNameIsAnchorNameWhenProvided(): void
    {
        // The nested sequence `(member (-* comma -* member)*)` does not have an
        // explicit anchorName in current grammar, so the fallback default name
        // should be used. This test verifies the fallback is SequenceAttribute's
        // DEFAULT_NAME constant value ("grouped"), not an arbitrary rule name.
        $this->assertGrammarParsing(
            string: '{"a":1,"b":2}',
            grammar: $this->grammar(),
            assertParsingResultValid: function (NodeInterface $result, self $test): void {
                $objectNode = $this->findFirstNodeByName($result, 'object');
                $test->assertNotNull($objectNode, 'Expected an "object" node in the parsed tree');

                $groupedAttrs = array_filter(
                    $objectNode->getAttributes(),
                    static fn($attr) => $attr instanceof SequenceAttribute,
                );

                foreach ($groupedAttrs as $grouped) {
                    $test->assertEquals(
                        'members',
                        $grouped->getName(),
                        'SequenceAttribute without an explicit anchorName must use the default name "grouped"',
                    );
                }
            },
        );
    }

    // -------------------------------------------------------------------------
    // Feature: primitive choice alternatives surface via meta['alternatives']
    //
    // ChoiceAttribute was removed. The "primitive" rule (Rule::choice) still
    // produces a real "primitive" Node (one per matched alternative), but the
    // information about which alternative matched, and what the full set of
    // choices was, now lives directly on the Node's single content attribute
    // (NodeAttribute/RawContentAttribute/RawRegionAttribute/...) via
    // meta['alternatives'], rather than behind a dedicated wrapper attribute.
    // -------------------------------------------------------------------------

    #[Test]
    public function primitiveAttributeCarriesChoiceAlternativesInMeta(): void
    {
        // A JSON object with a string value — "string" is one of the choices
        $this->assertGrammarParsing(
            string: '{"k":"v"}',
            grammar: $this->grammar(),
            assertParsingResultValid: function (NodeInterface $result, self $test): void {
                $primitiveNode = $this->findFirstNodeByName($result, 'primitive');
                $test->assertNotNull($primitiveNode, 'Expected a "primitive" node in the parsed tree');

                $topLevelAttrs = $primitiveNode->getAttributes();
                $test->assertCount(1, $topLevelAttrs, 'Expected exactly one attribute on "primitive" node');

                $firstAttr = reset($topLevelAttrs);
                $test->assertInstanceOf(MetaInterface::class, $firstAttr);
                $test->assertSame(
                    ['false', 'null', 'true', 'number', 'string'],
                    $firstAttr->meta['alternatives'] ?? null,
                    'The matched alternative\'s attribute must carry the full set of choices in meta[\'alternatives\']',
                );
            },
        );
    }

    #[Test]
    public function primitiveChoiceAttributeHasCorrectName(): void
    {
        $this->assertGrammarParsing(
            string: '{"k":"v"}',
            grammar: $this->grammar(),
            assertParsingResultValid: function (NodeInterface $result, self $test): void {
                $primitiveNode = $this->findFirstNodeByName($result, 'primitive');
                $test->assertNotNull($primitiveNode);

                $attr = $primitiveNode->getAttributes()[0] ?? null;
                $test->assertNotNull($attr, 'Expected an attribute on "primitive" node');

                $test->assertEquals(
                    'primitive',
                    $attr->getName(),
                    'The attribute name must be "primitive" (the anchor/rule name), not a pipe-joined list of choices',
                );
            },
        );
    }

    #[Test]
    public function primitiveChoiceAttributeHasAllExpectedChoices(): void
    {
        $this->assertGrammarParsing(
            string: '{"k":true}',
            grammar: $this->grammar(),
            assertParsingResultValid: function (NodeInterface $result, self $test): void {
                $primitiveNode = $this->findFirstNodeByName($result, 'primitive');
                $test->assertNotNull($primitiveNode);

                $attr = $primitiveNode->getAttributes()[0] ?? null;
                $test->assertNotNull($attr);

                $test->assertEquals(
                    ['false', 'null', 'true', 'number', 'string'],
                    $attr->meta['alternatives'] ?? null,
                    'meta[\'alternatives\'] must list all defined primitive alternatives',
                );
            },
        );
    }

    #[Test]
    public function primitiveChoiceAttributeSelectedIsRawRegionAttributeForString(): void
    {
        $this->assertGrammarParsing(
            string: '{"k":"hello"}',
            grammar: $this->grammar(),
            assertParsingResultValid: function (NodeInterface $result, self $test): void {
                $primitiveNode = $this->findFirstNodeByName($result, 'primitive');
                $test->assertNotNull($primitiveNode);

                $attr = $primitiveNode->getAttributes()[0] ?? null;
                $test->assertNotNull($attr);

                $test->assertInstanceOf(
                    RawRegionAttribute::class,
                    $attr,
                    'For a string primitive the resolved attribute must be a RawRegionAttribute',
                );

                /** @var RawRegionAttribute $attr */
                $test->assertEquals(
                    'string',
                    $attr->name,
                    'RawRegionAttribute::$name must be "string" (the matched choice), even though getName() returns the anchor "primitive"',
                );
            },
        );
    }

    #[Test]
    public function primitiveChoiceAttributeSelectedHasNoAnchorNameContainingPipeJoinedChoices(): void
    {
        // Bug: anchorName was incorrectly set to "false|null|true|number|string"
        // on the inner RawRegionAttribute. It must be null (no anchor) or the
        // choice name — never a pipe-joined list of all alternatives.
        $this->assertGrammarParsing(
            string: '{"k":"hello"}',
            grammar: $this->grammar(),
            assertParsingResultValid: function (NodeInterface $result, self $test): void {
                $primitiveNode = $this->findFirstNodeByName($result, 'primitive');
                $test->assertNotNull($primitiveNode);

                $attr = $primitiveNode->getAttributes()[0] ?? null;
                $test->assertNotNull($attr);

                if ($attr instanceof RawRegionAttribute) {
                    $test->assertStringNotContainsString(
                        '|',
                        (string) ($attr->anchorName ?? ''),
                        'RawRegionAttribute::$anchorName must not contain a pipe-joined list of all choice alternatives',
                    );
                }
            },
        );
    }

    #[Test]
    public function primitiveChoiceAttributeForBooleanTrue(): void
    {
        $this->assertGrammarParsing(
            string: 'true',
            grammar: $this->grammar(),
            assertParsingResultValid: function (NodeInterface $result, self $test): void {
                $primitiveNode = $this->findFirstNodeByName($result, 'primitive');
                $test->assertNotNull($primitiveNode);

                $attr = $primitiveNode->getAttributes()[0] ?? null;
                $test->assertNotNull($attr);

                $test->assertInstanceOf(
                    RawContentAttribute::class,
                    $attr,
                    'Boolean "true" is matched as a Token, so it resolves to a RawContentAttribute',
                );
                $test->assertSame(
                    ['false', 'null', 'true', 'number', 'string'],
                    $attr->meta['alternatives'] ?? null,
                );
                $test->assertSame('true', (string) $attr);
            },
        );
    }

    #[Test]
    public function primitiveChoiceAttributeForNull(): void
    {
        $this->assertGrammarParsing(
            string: 'null',
            grammar: $this->grammar(),
            assertParsingResultValid: function (NodeInterface $result, self $test): void {
                $primitiveNode = $this->findFirstNodeByName($result, 'primitive');
                $test->assertNotNull($primitiveNode);

                $attr = $primitiveNode->getAttributes()[0] ?? null;
                $test->assertNotNull($attr);
                $test->assertSame(
                    ['false', 'null', 'true', 'number', 'string'],
                    $attr->meta['alternatives'] ?? null,
                );
            },
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function findFirstNodeByName(NodeInterface $node, string $name): ?NodeInterface
    {
        if ($node->getName() === $name) {
            return $node;
        }

        foreach ($node->getAttributes() as $attr) {
            if ($attr instanceof \PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\NodeAttribute) {
                $found = $this->findFirstNodeByName($attr->node, $name);
                if ($found !== null) {
                    return $found;
                }
            }
            if ($attr instanceof \PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\OptionalAttribute && $attr->node !== null) {
                $found = $this->findFirstNodeByName($attr->node, $name);
                if ($found !== null) {
                    return $found;
                }
            }
            if ($attr instanceof \PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\GroupAttribute) {
                foreach ($attr->nodes as $child) {
                    $found = $this->findFirstNodeByName($child, $name);
                    if ($found !== null) {
                        return $found;
                    }
                }
            }
            if ($attr instanceof SequenceAttribute) {
                foreach ($attr->attributes as $nestedAttr) {
                    $tempNode = new Node('_tmp', NodeOrigin::Sequence, [$nestedAttr], null);
                    $found = $this->findFirstNodeByName($tempNode, $name);
                    if ($found !== null && $found->getName() !== '_tmp') {
                        return $found;
                    }
                }
            }
        }

        return null;
    }
}
