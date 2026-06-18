<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Func\Grammar\TreeSchema;

use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\ChoiceAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\GroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\SequenceAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\NodeAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Node;
use PhpArchitecture\Parser\Foundation\Parsing\Model\SequenceNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Json\JsonRfc8259;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\EmptyLineNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\InlineWsNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\LeadingWsNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\TrailingWsNode;
use PhpArchitecture\Parser\Infrastructure\TreeSchema\Model\Json\Rfc8259\ArrayNode;
use PhpArchitecture\Parser\Infrastructure\TreeSchema\Model\Json\Rfc8259\JsonNode;
use PhpArchitecture\Parser\Infrastructure\TreeSchema\Model\Json\Rfc8259\MemberNode;
use PhpArchitecture\Parser\Infrastructure\TreeSchema\Model\Json\Rfc8259\ObjectNode;
use PhpArchitecture\Parser\Infrastructure\TreeSchema\Model\Json\Rfc8259\PrimitiveNode;
use PhpArchitecture\Parser\Tests\Func\Grammar\GrammarTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('func')]
final class JsonRfc8259FacadeNodeTest extends GrammarTestCase
{
    // -------------------------------------------------------------------------
    // Root and core node classes
    // -------------------------------------------------------------------------

    #[Test]
    public function rootNodeIsJsonNode(): void
    {
        $this->assertGrammarParsing(
            string: '{"a":1}',
            grammar: (new JsonRfc8259())->grammar(),
            assertParsingResultValid: function (NodeInterface $result, self $test): void {
                $test->assertInstanceOf(JsonNode::class, $result);
            },
        );
    }

    #[Test]
    public function objectNodeIsObjectNode(): void
    {
        $this->assertGrammarParsing(
            string: '{"a":1}',
            grammar: (new JsonRfc8259())->grammar(),
            assertParsingResultValid: function (NodeInterface $result, self $test): void {
                $test->assertInstanceOf(JsonNode::class, $result);
                $objectNode = $this->firstChildNodeByName($result, 'object');
                $test->assertInstanceOf(ObjectNode::class, $objectNode);
            },
        );
    }

    #[Test]
    public function memberNodeIsMemberNode(): void
    {
        $this->assertGrammarParsing(
            string: '{"key":"val"}',
            grammar: (new JsonRfc8259())->grammar(),
            assertParsingResultValid: function (NodeInterface $result, self $test): void {
                $member = $this->firstChildNodeByName($result, 'member');
                $test->assertInstanceOf(MemberNode::class, $member);
            },
        );
    }

    #[Test]
    public function primitiveNodeIsPrimitiveNode(): void
    {
        $this->assertGrammarParsing(
            string: 'true',
            grammar: (new JsonRfc8259())->grammar(),
            assertParsingResultValid: function (NodeInterface $result, self $test): void {
                $primitive = $this->firstChildNodeByName($result, 'primitive');
                $test->assertInstanceOf(PrimitiveNode::class, $primitive);
            },
        );
    }

    #[Test]
    public function arrayNodeIsArrayNode(): void
    {
        $this->assertGrammarParsing(
            string: '[1,2]',
            grammar: (new JsonRfc8259())->grammar(),
            assertParsingResultValid: function (NodeInterface $result, self $test): void {
                $array = $this->firstChildNodeByName($result, 'array');
                $test->assertInstanceOf(ArrayNode::class, $array);
            },
        );
    }

    // -------------------------------------------------------------------------
    // Whitespace node classes
    // -------------------------------------------------------------------------

    #[Test]
    public function trailingWsNodesAreTrailingWsNode(): void
    {
        $this->assertGrammarParsing(
            string: "{\n\"a\":1\n}",
            grammar: (new JsonRfc8259())->grammar(),
            assertParsingResultValid: function (NodeInterface $result, self $test): void {
                $wsNodes = $this->collectWhitespaceNodes($result);
                $test->assertNotEmpty($wsNodes, 'Expected at least one whitespace node in parse tree');
                foreach ($wsNodes as $ws) {
                    $test->assertNotSame(
                        Node::class,
                        get_class($ws),
                        'Whitespace nodes must be facade instances, not bare Node (got ' . get_class($ws) . ')',
                    );
                }
            },
        );
    }

    #[Test]
    public function whitespaceNodesAreCorrectFacadeClasses(): void
    {
        $this->assertGrammarParsing(
            string: "{\n  \"a\": 1\n}",
            grammar: (new JsonRfc8259())->grammar(),
            assertParsingResultValid: function (NodeInterface $result, self $test): void {
                $wsNodes = $this->collectWhitespaceNodes($result);
                $test->assertNotEmpty($wsNodes);
                $allowedClasses = [LeadingWsNode::class, TrailingWsNode::class, InlineWsNode::class, EmptyLineNode::class];
                foreach ($wsNodes as $ws) {
                    $test->assertContains(
                        get_class($ws),
                        $allowedClasses,
                        'Whitespace node must be one of the 4 facade classes, got ' . get_class($ws),
                    );
                }
            },
        );
    }

    // -------------------------------------------------------------------------
    // Facade method accessibility
    // -------------------------------------------------------------------------

    #[Test]
    public function objectNodeFacadeMethodsWork(): void
    {
        $this->assertGrammarParsing(
            string: '{"x":1,"y":2}',
            grammar: (new JsonRfc8259())->grammar(),
            assertParsingResultValid: function (NodeInterface $result, self $test): void {
                $test->assertInstanceOf(JsonNode::class, $result);
                $objectNode = $this->firstChildNodeByName($result, 'object');
                $test->assertInstanceOf(ObjectNode::class, $objectNode);

                $members = $objectNode->getMembers();
                $test->assertCount(2, $members);
                $test->assertContainsOnlyInstancesOf(MemberNode::class, $members);
                $test->assertSame('x', $members[0]->getRawIdentifier());
                $test->assertSame('y', $members[1]->getRawIdentifier());
            },
        );
    }

    #[Test]
    public function memberNodeFacadeMethodsWork(): void
    {
        $this->assertGrammarParsing(
            string: '{"hello":"world"}',
            grammar: (new JsonRfc8259())->grammar(),
            assertParsingResultValid: function (NodeInterface $result, self $test): void {
                $member = $this->firstChildNodeByName($result, 'member');
                $test->assertInstanceOf(MemberNode::class, $member);
                $test->assertSame('hello', $member->getRawIdentifier());

                $value = $member->getNodeValue();
                $test->assertInstanceOf(PrimitiveNode::class, $value);
            },
        );
    }

    // -------------------------------------------------------------------------
    // No facade classes — plain Node when nodeClassMap is empty
    // -------------------------------------------------------------------------

    #[Test]
    public function withoutNodeClassMapRootIsPlainNode(): void
    {
        $grammar = (new JsonRfc8259())->grammar();
        $grammar->nodeClassMap = [];

        $this->assertGrammarParsing(
            string: '{"a":1}',
            grammar: $grammar,
            assertParsingResultValid: function (NodeInterface $result, self $test): void {
                $test->assertSame(SequenceNode::class, get_class($result), 'Without nodeClassMap root must be a plain shape node (Sequence origin)');
            },
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function firstChildNodeByName(NodeInterface $root, string $name): ?NodeInterface
    {
        foreach ($root->getAttributes() as $attr) {
            if ($attr instanceof NodeAttribute) {
                if ($attr->node->getName() === $name) {
                    return $attr->node;
                }
                $found = $this->firstChildNodeByName($attr->node, $name);
                if ($found !== null) {
                    return $found;
                }
            }
            if ($attr instanceof ChoiceAttribute && $attr->selected instanceof NodeAttribute) {
                if ($attr->selected->node->getName() === $name) {
                    return $attr->selected->node;
                }
                $found = $this->firstChildNodeByName($attr->selected->node, $name);
                if ($found !== null) {
                    return $found;
                }
            }
            if ($attr instanceof SequenceAttribute) {
                foreach ($attr->attributes as $nested) {
                    if ($nested instanceof NodeAttribute) {
                        if ($nested->node->getName() === $name) {
                            return $nested->node;
                        }
                        $found = $this->firstChildNodeByName($nested->node, $name);
                        if ($found !== null) {
                            return $found;
                        }
                    }
                }
            }
        }
        return null;
    }

    /** @return NodeInterface[] */
    private function collectWhitespaceNodes(NodeInterface $root): array
    {
        $result = [];
        $wsNames = ['leadingWs', 'trailingWs', 'emptyLine', 'inlineWs'];

        foreach ($root->getAttributes() as $attr) {
            if ($attr instanceof GroupAttribute) {
                foreach ($attr->nodes as $node) {
                    if (in_array($node->getName(), $wsNames, true)) {
                        $result[] = $node;
                    }
                    array_push($result, ...$this->collectWhitespaceNodes($node));
                }
            }
            if ($attr instanceof NodeAttribute) {
                array_push($result, ...$this->collectWhitespaceNodes($attr->node));
            }
            if ($attr instanceof ChoiceAttribute && $attr->selected instanceof NodeAttribute) {
                array_push($result, ...$this->collectWhitespaceNodes($attr->selected->node));
            }
            if ($attr instanceof SequenceAttribute) {
                foreach ($attr->attributes as $nested) {
                    if ($nested instanceof NodeAttribute) {
                        array_push($result, ...$this->collectWhitespaceNodes($nested->node));
                    }
                }
            }
        }

        return $result;
    }
}
