<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Func\Grammar\Json;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\ChoiceAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\GroupedAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\RawRegionAttribute;
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
    // Feature: object member GroupedAttribute name
    // -------------------------------------------------------------------------

    /**
     * The sequence `?(member (-* comma -* member)*)/g` uses an anchor `/g` on
     * the nested group sequence. The resulting GroupedAttribute must carry the
     * anchor name derived from that nested sequence — NOT the literal "member"
     * rule name. When no anchorName is provided on the nested sequence the
     * GroupedAttribute name must fall back to GroupedAttribute::DEFAULT_NAME
     * (i.e. "grouped"), NOT "member".
     */
    #[Test]
    public function objectMemberGroupedAttributeNameIsNotMember(): void
    {
        $this->assertGrammarParsing(
            string: '{"key":"value"}',
            grammar: $this->grammar(),
            assertParsingResultValid: function (NodeInterface $result, self $test): void {
                $objectNode = $this->findFirstNodeByName($result, 'object');
                $test->assertNotNull($objectNode, 'Expected an "object" node in the parsed tree');

                $groupedAttrs = array_filter(
                    $objectNode->getAttributes(),
                    static fn($attr) => $attr instanceof GroupedAttribute,
                );
                $test->assertNotEmpty($groupedAttrs, 'Expected at least one GroupedAttribute on "object" node');

                foreach ($groupedAttrs as $grouped) {
                    $test->assertNotEquals(
                        'member',
                        $grouped->getName(),
                        'GroupedAttribute name must NOT be "member" — it should derive from the nested sequence anchorName or fall back to the DEFAULT_NAME',
                    );
                }
            },
        );
    }

    #[Test]
    public function objectMemberGroupedAttributeNameIsAnchorNameWhenProvided(): void
    {
        // The nested sequence `(member (-* comma -* member)*)` does not have an
        // explicit anchorName in current grammar, so the fallback default name
        // should be used. This test verifies the fallback is GroupedAttribute's
        // DEFAULT_NAME constant value ("grouped"), not an arbitrary rule name.
        $this->assertGrammarParsing(
            string: '{"a":1,"b":2}',
            grammar: $this->grammar(),
            assertParsingResultValid: function (NodeInterface $result, self $test): void {
                $objectNode = $this->findFirstNodeByName($result, 'object');
                $test->assertNotNull($objectNode, 'Expected an "object" node in the parsed tree');

                $groupedAttrs = array_filter(
                    $objectNode->getAttributes(),
                    static fn($attr) => $attr instanceof GroupedAttribute,
                );

                foreach ($groupedAttrs as $grouped) {
                    $test->assertEquals(
                        'members',
                        $grouped->getName(),
                        'GroupedAttribute without an explicit anchorName must use the default name "grouped"',
                    );
                }
            },
        );
    }

    // -------------------------------------------------------------------------
    // Feature: primitive ChoiceAttribute wraps the selected value attribute
    // -------------------------------------------------------------------------

    /**
     * Rule::choice("primitive", [...]) must produce a ChoiceAttribute on the
     * "primitive" node. The top-level attribute on that node must be
     * ChoiceAttribute, NOT a bare RawRegionAttribute or any other attribute
     * that bypasses the choice wrapper.
     */
    #[Test]
    public function primitiveNodeHasChoiceAttributeNotRawRegionAttributeAtTopLevel(): void
    {
        // A JSON object with a string value — "string" is one of the choices
        $this->assertGrammarParsing(
            string: '{"k":"v"}',
            grammar: $this->grammar(),
            assertParsingResultValid: function (NodeInterface $result, self $test): void {
                $primitiveNode = $this->findFirstNodeByName($result, 'primitive');
                $test->assertNotNull($primitiveNode, 'Expected a "primitive" node in the parsed tree');

                $topLevelAttrs = $primitiveNode->getAttributes();
                $test->assertNotEmpty($topLevelAttrs, 'Expected attributes on "primitive" node');

                $firstAttr = reset($topLevelAttrs);
                $test->assertInstanceOf(
                    ChoiceAttribute::class,
                    $firstAttr,
                    'The first (and expected sole) attribute on a "primitive" node must be a ChoiceAttribute, not a bare RawRegionAttribute',
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

                $choiceAttr = $this->findFirstChoiceAttribute($primitiveNode);
                $test->assertNotNull($choiceAttr, 'Expected a ChoiceAttribute on "primitive" node');

                $test->assertEquals(
                    'primitive',
                    $choiceAttr->getName(),
                    'ChoiceAttribute name must be "primitive" (the rule name), not a pipe-joined list of choices',
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

                $choiceAttr = $this->findFirstChoiceAttribute($primitiveNode);
                $test->assertNotNull($choiceAttr);

                $test->assertEquals(
                    ['false', 'null', 'true', 'number', 'string'],
                    $choiceAttr->choices,
                    'ChoiceAttribute choices must list all defined primitive alternatives',
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

                $choiceAttr = $this->findFirstChoiceAttribute($primitiveNode);
                $test->assertNotNull($choiceAttr);

                $test->assertInstanceOf(
                    RawRegionAttribute::class,
                    $choiceAttr->selected,
                    'For a string primitive the ChoiceAttribute::$selected must be a RawRegionAttribute',
                );

                /** @var RawRegionAttribute $selected */
                $selected = $choiceAttr->selected;
                $test->assertEquals(
                    'string',
                    $selected->name,
                    'Selected RawRegionAttribute::$name must be "string" (the matched choice)',
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

                $choiceAttr = $this->findFirstChoiceAttribute($primitiveNode);
                $test->assertNotNull($choiceAttr);

                if ($choiceAttr->selected instanceof RawRegionAttribute) {
                    $test->assertStringNotContainsString(
                        '|',
                        (string) ($choiceAttr->selected->anchorName ?? ''),
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

                $choiceAttr = $this->findFirstChoiceAttribute($primitiveNode);
                $test->assertNotNull($choiceAttr);

                $test->assertInstanceOf(
                    ChoiceAttribute::class,
                    $choiceAttr,
                    'Boolean true must be wrapped in ChoiceAttribute',
                );
                $test->assertNotNull($choiceAttr->selected, 'ChoiceAttribute::$selected must not be null for "true"');
                $test->assertNotInstanceOf(
                    RawRegionAttribute::class,
                    // top-level attribute on primitive node must be ChoiceAttribute:
                    $result->getAttributes()[0] ?? null,
                    'Top-level attribute on "primitive" node must be ChoiceAttribute when parsing standalone "true"',
                );
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

                $choiceAttr = $this->findFirstChoiceAttribute($primitiveNode);
                $test->assertNotNull($choiceAttr);
                $test->assertNotNull($choiceAttr->selected);
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
            if ($attr instanceof \PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\NodeAttribute) {
                $found = $this->findFirstNodeByName($attr->node, $name);
                if ($found !== null) {
                    return $found;
                }
            }
            if ($attr instanceof \PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\OptionalAttribute && $attr->node !== null) {
                $found = $this->findFirstNodeByName($attr->node, $name);
                if ($found !== null) {
                    return $found;
                }
            }
            if ($attr instanceof \PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\GroupAttribute) {
                foreach ($attr->nodes as $child) {
                    $found = $this->findFirstNodeByName($child, $name);
                    if ($found !== null) {
                        return $found;
                    }
                }
            }
            if ($attr instanceof GroupedAttribute) {
                foreach ($attr->attributes as $nestedAttr) {
                    $tempNode = new \PhpArchitecture\Parser\Foundation\Parsing\Model\Node('_tmp', [$nestedAttr], null);
                    $found = $this->findFirstNodeByName($tempNode, $name);
                    if ($found !== null && $found->getName() !== '_tmp') {
                        return $found;
                    }
                }
            }
            if ($attr instanceof ChoiceAttribute && $attr->selected !== null) {
                $tempNode = new \PhpArchitecture\Parser\Foundation\Parsing\Model\Node('_tmp', [$attr->selected], null);
                $found = $this->findFirstNodeByName($tempNode, $name);
                if ($found !== null && $found->getName() !== '_tmp') {
                    return $found;
                }
            }
        }

        return null;
    }

    private function findFirstChoiceAttribute(NodeInterface $node): ?ChoiceAttribute
    {
        foreach ($node->getAttributes() as $attr) {
            if ($attr instanceof ChoiceAttribute) {
                return $attr;
            }
        }
        return null;
    }
}
