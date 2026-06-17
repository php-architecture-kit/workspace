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
                ->setNodeType(NodeType::Node)
                ->addTag("comment", "-", "-l", "-t"),

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
                ->setNodeType(NodeType::Node)
                ->addTag("comment", "-", "-l", "-t"),
        );

        $this->grammar->stampOrigin(new GrammarOrigin(self::FORMAT, self::VARIANT));

        return $this->grammar;
    }
}
