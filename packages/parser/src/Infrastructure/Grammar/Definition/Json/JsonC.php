<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Json;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\GrammarOrigin;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeType;

class JsonC extends JsonRfc8259
{
    public const FORMAT = "json";
    public const VARIANT = "c";

    public function grammar(): Grammar
    {
        parent::grammar();

        $this->grammar->global->add(
            Rule::expr("lineComment", "//[^\n]*")
                ->startRegion('lineComment', true)
                ->closeWith(Rule::ref("lineComment"), false, true, true)
                ->retokenizedByInnerGrammar((new JsonComment('lineComment'))->grammar())
                ->setNodeType(NodeType::Node),
            Rule::token("blockCommentStart", "/*", type: NodeType::Structure)
                ->startRegion('blockComment', true)
                ->retokenizedByInnerGrammar((new JsonComment('blockComment'))->grammar())
                ->add(
                    Rule::ref("eof")->closeRegion(false, false, true),
                    Rule::token("blockCommentEnd", "*/", type: NodeType::Structure)
                        ->priority(1)
                        ->closeRegion(true, false, false),
                    Rule::expr("commentContent", "(?:[^*]|\*(?!/))+"),
                )
                ->setNodeType(NodeType::Node),
            Rule::seq("leadingComment", "?leadingWs|inlineWs[leadingWs] lineComment|blockComment[comment] inlineWs|trailingWs*[trailingWs]", ['-', '-l', 'comment']),
            Rule::seq("trailingComment", "?inlineWs[leadingWs] lineComment|blockComment[comment] inlineWs|trailingWs*[trailingWs]", ['-', '-t', 'comment']),
        );

        $this->grammar->stampOrigin(new GrammarOrigin(self::FORMAT, self::VARIANT));

        $this->grammar->nodeClassMap = array_merge($this->grammar->nodeClassMap, [
            'json' => \PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\C\JsonNode::class,
            'leadingComment' => \PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\C\LeadingCommentNode::class,
            'blockComment' => \PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\C\BlockCommentNode::class,
            'lineComment' => \PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\C\LineCommentNode::class,
            'object' => \PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\C\ObjectNode::class,
            'member' => \PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\C\MemberNode::class,
            'primitive' => \PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\C\PrimitiveNode::class,
            'trailingComment' => \PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\C\TrailingCommentNode::class,
            'array' => \PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\C\ArrayNode::class,
        ]);

        return $this->grammar;
    }
}
