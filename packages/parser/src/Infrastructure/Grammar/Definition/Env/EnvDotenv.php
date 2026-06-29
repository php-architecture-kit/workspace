<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Env;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\GrammarOrigin;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeType;

class EnvDotenv extends EnvEnvironment
{
    public const FORMAT = 'env';
    public const VARIANT = 'dotenv';

    public function grammar(): Grammar
    {
        $grammar = parent::grammar();
        $regions = $grammar->getAllRegions();

        $regions['value']->add(
            Rule::token("singleQuote", "'", type: NodeType::Structure)
                ->priority(1)
                ->startRegion('singleQuotedValue', true)
                ->add(
                    Rule::expr("singleQuotedContent", "[^']+"),
                    Rule::token("singleQuote", "'", type: NodeType::Structure)->priority(1)->closeRegion(true, false, false),
                )
                ->withRootSequence("singleQuote ?singleQuotedContent singleQuote")
                ->setNodeType(NodeType::Raw),
            Rule::token("doubleQuote", '"', type: NodeType::Structure)
                ->priority(1)
                ->startRegion('doubleQuotedValue', true)
                ->add(
                    Rule::expr("escapeChar", '\\\\[nrbt"\\\\$]')->priority(1),
                    Rule::token("lineContinuation", "\\\n")->priority(1),
                    Rule::expr("simpleExpansion", '\\$[a-zA-Z_][a-zA-Z0-9_]*'),
                    Rule::expr("bracedExpansion", '\\$\\{[a-zA-Z_][a-zA-Z0-9_]*(?::?[-+?=][^}]*)?\\}'),
                    Rule::expr("doubleQuotedContent", "[^\"\\\\$\n]+")->priority(0),
                    Rule::token("doubleQuote", '"', type: NodeType::Structure)->priority(1)->closeRegion(true, false, false),
                )
                ->setNodeType(NodeType::Node),
        );

        $grammar->stampOrigin(new GrammarOrigin(self::FORMAT, self::VARIANT), false, ['value']);

        $grammar->nodeClassMap = array_merge($grammar->nodeClassMap, [
            'lineComment' => \PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Env\Dotenv\LineCommentNode::class,
            'assignment' => \PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Env\Dotenv\AssignmentNode::class,
            'value' => \PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Env\Dotenv\ValueNode::class,
            'doubleQuotedValue' => \PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Env\Dotenv\DoubleQuotedValueNode::class,
            'simpleExpansion' => \PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Env\Dotenv\SimpleExpansionNode::class,
            'bracedExpansion' => \PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Env\Dotenv\BracedExpansionNode::class,
            'envExpression' => \PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Env\Dotenv\EnvExpressionNode::class,
        ]);

        return $grammar;
    }
}
