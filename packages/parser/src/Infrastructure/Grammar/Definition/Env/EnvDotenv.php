<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Env;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
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

        $regions['assignment']->addRule(
            Rule::expr("bracedExpansion", "\\$\\{[a-zA-Z_][a-zA-Z0-9_]*(?::?[-+?=][^}]*)?\\}"),
        );

        $regions['assignment']->addRule(
            Rule::expr("unquotedText", "[^\n$\t ='\"]+"),
        );

        $regions['assignment']->add(
            Rule::token("singleQuote", "'", type: NodeType::Structure)
                ->startRegion('singleQuotedValue', true)
                ->add(
                    Rule::expr("singleQuotedContent", "[^']+"),
                )
                ->setNodeType(NodeType::Raw)
                ->closeWith(Rule::token("singleQuote", "'", type: NodeType::Structure)),
            Rule::token("doubleQuote", '"', type: NodeType::Structure)
                ->startRegion('doubleQuotedValue', true)
                ->add(
                    Rule::expr("escapeChar", '\\\\[nrbt"\\\\$]')->priority(1),
                    Rule::expr("lineContinuation", "\\\\\n")->priority(1),
                    Rule::expr("simpleExpansion", '\\$[a-zA-Z_][a-zA-Z0-9_]*'),
                    Rule::expr("bracedExpansion", '\\$\\{[a-zA-Z_][a-zA-Z0-9_]*(?::?[-+?=][^}]*)?\\}'),
                    Rule::expr("doubleQuotedContent", '[^"\\\\$\n]+'),
                )
                ->setNodeType(NodeType::Raw)
                ->closeWith(Rule::token("doubleQuote", '"', type: NodeType::Structure)),
        );

        $regions['assignment']->withRootSequence(
            "identifier (space|tab)* equals (space|tab)* "
            . "(singleQuotedValue|doubleQuotedValue|simpleExpansion|bracedExpansion|unquotedText|equals|space|tab)* "
            . "newline|eof",
        );

        return $grammar;
    }
}
