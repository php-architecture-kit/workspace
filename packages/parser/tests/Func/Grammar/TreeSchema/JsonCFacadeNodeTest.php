<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Func\Grammar\TreeSchema;

use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\ChoiceAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\GroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\SequenceAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\NodeAttribute;
use PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Json\JsonC;
use PhpArchitecture\Parser\Infrastructure\TreeSchema\Model\Json\C\ArrayNode;
use PhpArchitecture\Parser\Infrastructure\TreeSchema\Model\Json\C\BlockCommentNode;
use PhpArchitecture\Parser\Infrastructure\TreeSchema\Model\Json\C\JsonNode;
use PhpArchitecture\Parser\Infrastructure\TreeSchema\Model\Json\C\MemberNode;
use PhpArchitecture\Parser\Infrastructure\TreeSchema\Model\Json\C\ObjectNode;
use PhpArchitecture\Parser\Infrastructure\TreeSchema\Model\Json\C\PrimitiveNode;
use PhpArchitecture\Parser\Tests\Func\Grammar\GrammarTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('func')]
final class JsonCFacadeNodeTest extends GrammarTestCase
{
    // -------------------------------------------------------------------------
    // Core node classes (C namespace, not Rfc8259)
    // -------------------------------------------------------------------------

    #[Test]
    public function rootNodeIsJsonCNode(): void
    {
        $this->assertGrammarParsing(
            string: '{"a":1}',
            grammar: (new JsonC())->grammar(),
            assertParsingResultValid: function (NodeInterface $result, self $test): void {
                $test->assertInstanceOf(JsonNode::class, $result);
            },
        );
    }

    #[Test]
    public function objectNodeIsObjectCNode(): void
    {
        $this->assertGrammarParsing(
            string: '{"a":1}',
            grammar: (new JsonC())->grammar(),
            assertParsingResultValid: function (NodeInterface $result, self $test): void {
                $obj = $this->firstChildNodeByName($result, 'object');
                $test->assertInstanceOf(ObjectNode::class, $obj);
            },
        );
    }

    #[Test]
    public function memberNodeIsMemberCNode(): void
    {
        $this->assertGrammarParsing(
            string: '{"k":"v"}',
            grammar: (new JsonC())->grammar(),
            assertParsingResultValid: function (NodeInterface $result, self $test): void {
                $member = $this->firstChildNodeByName($result, 'member');
                $test->assertInstanceOf(MemberNode::class, $member);
            },
        );
    }

    #[Test]
    public function primitiveNodeIsPrimitiveCNode(): void
    {
        $this->assertGrammarParsing(
            string: 'null',
            grammar: (new JsonC())->grammar(),
            assertParsingResultValid: function (NodeInterface $result, self $test): void {
                $primitive = $this->firstChildNodeByName($result, 'primitive');
                $test->assertInstanceOf(PrimitiveNode::class, $primitive);
            },
        );
    }

    #[Test]
    public function arrayNodeIsArrayCNode(): void
    {
        $this->assertGrammarParsing(
            string: '[1]',
            grammar: (new JsonC())->grammar(),
            assertParsingResultValid: function (NodeInterface $result, self $test): void {
                $array = $this->firstChildNodeByName($result, 'array');
                $test->assertInstanceOf(ArrayNode::class, $array);
            },
        );
    }

    // -------------------------------------------------------------------------
    // BlockCommentNode
    // -------------------------------------------------------------------------

    #[Test]
    public function blockCommentNodeIsBlockCommentNode(): void
    {
        $this->assertGrammarParsing(
            string: "/* comment */\n{\"a\":1}",
            grammar: (new JsonC())->grammar(),
            assertParsingResultValid: function (NodeInterface $result, self $test): void {
                $comment = $this->firstChildNodeByName($result, 'blockComment');
                $test->assertInstanceOf(BlockCommentNode::class, $comment);
            },
        );
    }

    // -------------------------------------------------------------------------
    // C nodes differ from Rfc8259 nodes
    // -------------------------------------------------------------------------

    #[Test]
    public function cNodesAreNotRfc8259Instances(): void
    {
        $this->assertGrammarParsing(
            string: '{"a":1}',
            grammar: (new JsonC())->grammar(),
            assertParsingResultValid: function (NodeInterface $result, self $test): void {
                $test->assertNotInstanceOf(
                    \PhpArchitecture\Parser\Infrastructure\TreeSchema\Model\Json\Rfc8259\JsonNode::class,
                    $result,
                    'JSON C root must be the C\JsonNode class, not the Rfc8259\JsonNode class',
                );
                $test->assertNotInstanceOf(
                    \PhpArchitecture\Parser\Infrastructure\TreeSchema\Model\Json\Rfc8259\ObjectNode::class,
                    $this->firstChildNodeByName($result, 'object'),
                    'JSON C ObjectNode must be C\ObjectNode, not Rfc8259\ObjectNode',
                );
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
            if ($attr instanceof GroupAttribute) {
                foreach ($attr->nodes as $node) {
                    if ($node->getName() === $name) {
                        return $node;
                    }
                    $found = $this->firstChildNodeByName($node, $name);
                    if ($found !== null) {
                        return $found;
                    }
                }
            }
        }
        return null;
    }
}
