<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Env;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\GrammarOrigin;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Region;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeType;
use PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Technical\Whitespace;

class EnvEnvironment extends Whitespace
{
    public const FORMAT = 'env';
    public const VARIANT = 'environment';

    public function grammar(): Grammar
    {
        $grammar = parent::grammar();

        $grammar->global->add(
            Rule::expr("lineComment", "#[^\n]*")
                ->priority(-1)
                ->startRegion('lineComment', true)
                ->add(
                    Rule::token("newline", "\n", ["trailingWs"])->closeRegion(true, true, false),
                    Rule::technical("eof", ["trailingWs"])->closeRegion(true, true, false),
                )
                ->retokenizedByInnerGrammar((new EnvComment())->grammar())
                ->setNodeType(NodeType::Node)
                ->addTag("comment", "-"),

            Rule::expr("identifier", "[a-zA-Z_][a-zA-Z0-9_]*")
                ->startRegion('assignment', true)
                ->add(
                    Rule::token("assign", "=", type: NodeType::Structure),
                    (new Region("value"))
                        ->openWith(Rule::ref("assign"), false)
                        ->add(
                            Rule::token("dollar", "$")
                                ->startRegion("simpleExpansion", true)
                                ->add(
                                    Rule::expr("string", "[a-zA-Z_][a-zA-Z0-9_]*")->closeRegion(true, true, false),
                                )
                                ->withRootSequence("dollar string[varRef]/r")
                                ->setNodeType(NodeType::Node),
                            Rule::token("dollarBrace", "\${", type: NodeType::Structure)
                                ->priority(1)
                                ->startRegion("bracedExpansion", true)
                                ->reParsedByPratt("envExpression")
                                ->add(
                                    Rule::expr("string", "[a-zA-Z_][a-zA-Z0-9_]*")->prattAtom(),
                                    Rule::expr("unquotedText", "[^\n\$\t }:#=]+")->prattAtom(),
                                    Rule::token("space", " "),
                                    Rule::token("tab", "\t"),
                                    Rule::token("colonMinus", ":-", type: NodeType::Structure)->prattInfix(10),
                                    Rule::token("colonPlus", ":+", type: NodeType::Structure)->prattInfix(10),
                                    Rule::token("colonQuestion", ":?", type: NodeType::Structure)->prattInfix(10),
                                    Rule::token("colonAssign", ":=", type: NodeType::Structure)->prattInfix(10),
                                    Rule::token("closeBrace", "}", type: NodeType::Structure)->closeRegion(true, true, false),
                                )
                                ->setNodeType(NodeType::Node),
                            Rule::token("space", " ", ["inlineWs"]),
                            Rule::token("tab", "\t", ["inlineWs"]),
                            Rule::expr("unquotedText", "[^\n\$\t =#]+"),
                            Rule::token("newline", "\n", ["trailingWs"])->closeRegion(true, true, true),
                            Rule::technical("eof", ["trailingWs"])->closeRegion(true, true, true),
                        )
                        ->setNodeType(NodeType::Node),
                    Rule::token("space", " ", ["inlineWs"]),
                    Rule::token("tab", "\t", ["inlineWs"]),
                    Rule::token("newline", "\n", ["trailingWs"])->closeRegion(true, true, false),
                    Rule::technical("eof", ["trailingWs"])->closeRegion(true, true, false),
                )
                ->withRootSequence("identifier inlineWs*[trivia0]/r assign inlineWs*[trivia1]/r value trailingWs*[trivia2]/r")
                ->setNodeType(NodeType::Node),
        );

        $grammar->stampOrigin(new GrammarOrigin(self::FORMAT, self::VARIANT), false);

        $grammar->nodeClassMap = array_merge($grammar->nodeClassMap, [
            'lineComment' => \PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Env\Environment\LineCommentNode::class,
            'assignment' => \PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Env\Environment\AssignmentNode::class,
            'value' => \PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Env\Environment\ValueNode::class,
            'simpleExpansion' => \PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Env\Environment\SimpleExpansionNode::class,
            'bracedExpansion' => \PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Env\Environment\BracedExpansionNode::class,
            'envExpression' => \PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Env\Environment\EnvExpressionNode::class,
        ]);

        return $grammar;
    }
}
