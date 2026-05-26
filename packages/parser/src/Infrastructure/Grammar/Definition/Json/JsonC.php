<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Json;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeType;

class JsonC extends JsonRfc8259
{
    public const FORMAT = "json";
    public const VARIANT = "c";

    public function grammar(): Grammar
    {
        $grammar = parent::grammar();

        $blockLine = Rule::token("newline", "\n")
            ->startRegion('blockLine', false)
            ->add(
                Rule::token("space", " "),
                Rule::token("tab", "\t"),
                Rule::token("cr", "\r"),
                Rule::expr("asterisk", "\\*(?!/)"),
                Rule::expr("commentContent", "[^\r\n*/]+"),
                Rule::token("newline", "\n")->closeRegion(false, false, false),
            )
            ->closeWith(Rule::token("blockCommentEnd", "*/"), false, false);

        $grammar->global->add(
            Rule::token("lineCommentStart", "//", type: NodeType::Structure)
                ->startRegion('lineComment', true)
                ->add(
                    Rule::token("space", " "),
                    Rule::token("tab", "\t"),
                    Rule::token("cr", "\r"),
                    Rule::expr("commentContent", "[^\r\n]+"),
                    Rule::token("newline", "\n")->closeRegion(true, true, false),
                    Rule::technical("eof")->closeRegion(true, true, false),
                )
                ->withRootSequence("lineCommentStart (space|tab)* ?commentContent ?cr newline|eof")
                ->setNodeType(NodeType::Node)
                ->addTag("comment", "-"),

            Rule::token("blockCommentStart", "/*", type: NodeType::Structure)
                ->startRegion('blockComment', true)
                ->add(
                    Rule::token("space", " "),
                    Rule::token("tab", "\t"),
                    Rule::expr("asterisk", "\\*(?!/)"),
                    Rule::expr("commentContent", "[^\r\n*/]+"),
                    $blockLine,
                )
                ->withRootSequence("blockCommentStart (space|tab|asterisk|commentContent)* (newline blockLine)* blockCommentEnd")
                ->closeWith(Rule::token("blockCommentEnd", "*/", type: NodeType::Structure))
                ->setNodeType(NodeType::Node)
                ->addTag("comment", "-"),
        );

        return $grammar;
    }
}
