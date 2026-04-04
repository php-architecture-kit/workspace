<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Registry\Definition\Json;

use PhpArchitecture\Parser\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Grammar\Definition\Middleware\AddRuleMiddleware;
use PhpArchitecture\Parser\Grammar\Definition\Model\Sequence\NestedSequence;
use PhpArchitecture\Parser\Grammar\Definition\Model\Sequence\SequenceNode;
use PhpArchitecture\Parser\Grammar\Definition\Model\Sequence\SequenceRule;
use PhpArchitecture\Parser\Grammar\Definition\Region;
use PhpArchitecture\Parser\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Grammar\Definition\Service\SequenceExtender\SequenceExtender;
use PhpArchitecture\Parser\Grammar\Registry\Definition\Technical\Whitespace;
use PhpArchitecture\Parser\Processing\Model\Parsing\NodeType;

class JsonRfc8259 extends Whitespace
{
    public const FORMAT = "json";
    public const VARIANT = "rfc8259";

    public function grammar(): Grammar
    {
        $grammar = parent::grammar();

        $jsonText = new Region("json")
            ->setInheritanceFromGlobal()
            ->withRootSequence("ws* value ws*");

        $grammar->global->add(
            $jsonText,

            Rule::token("begin-array", "[", type: NodeType::Structure)
                ->startRegion('array')
                ->enableInheritanceFromGlobal()
                ->add(
                    $this->addTriviaMiddleware(),
                    Rule::token("value-separator", ",", type: NodeType::Structure),
                    Rule::seq("items", "value (value-separator value)*")
                )
                ->withRootSequence("begin-array items end-array")
                ->closeWith(
                    Rule::token("end-array", "]", type: NodeType::Structure),
                )
                ->addTag("value"),

            Rule::token("begin-object", "{", type: NodeType::Structure)
                ->startRegion('object')
                ->enableInheritanceFromGlobal()
                ->add(
                    $this->addTriviaMiddleware(),
                    Rule::token("name-separator", ":", type: NodeType::Structure),
                    Rule::token("value-separator", ",", type: NodeType::Structure),
                    Rule::seq("member", "string[identifier] name-separator value"),
                    Rule::seq("members", "member (value-separator member)*")
                )
                ->withRootSequence("begin-object members end-object")
                ->closeWith(
                    Rule::token("end-object", "}", type: NodeType::Structure),
                )
                ->addTag("value"),

            Rule::choice("primitive", ["false", "null", "true", "number", "string"], tags: ["value"]),

            Rule::keyword("null"),
            Rule::keyword("false"),
            Rule::keyword("true"),

            // string
            Rule::token("double-quote", "\"", type: NodeType::Structure)
                ->startRegion("string", true)
                ->add(
                    Rule::expr("escape-char", "\\\\[bfnrt\\\\\\\"]")->priority(1),
                    Rule::expr("unescaped", "[^\\x00-\\x1F\\x22\\x5C]+"),
                    Rule::expr("escape-unicode", "\\\\u[0-9a-fA-F]{4}"),
                )
                ->setNodeType(NodeType::Raw)
                ->closeWith(Rule::token("double-quote", "\"", type: NodeType::Structure)),

            // number
            Rule::token("decimal-point", ".", tags: ["_number_part"]),
            Rule::token("plus", "+", tags: ["_number_part"]),
            Rule::token("minus", "-", tags: ["_number_part"]),
            Rule::token("zero", "0", tags: ["_number_part"]),
            Rule::expr("digit1-9", "[1-9]", tags: ["_number_part"]),
            Rule::expr("e", "[eE]", tags: ["_number_part"]),
            Rule::taggedWith("_number_part")
                ->startRegion("number", true)
                ->add(
                    $this->addNodeTypeSetupForRules(NodeType::Raw),
                    Rule::token("decimal-point", ".", tags: ["_number_part"]),
                    Rule::token("plus", "+", tags: ["_number_part"]),
                    Rule::token("minus", "-", tags: ["_number_part"]),
                    Rule::token("zero", "0", tags: ["_number_part"]),
                    Rule::expr("digit1-9", "[1-9]", tags: ["_number_part"]),
                    Rule::expr("e", "[eE]", tags: ["_number_part"]),
                    Rule::seq("digit", "zero|digit1-9"),
                    Rule::seq("digit1-9-seq", "digit1-9 digit*"),
                    Rule::seq("exp", "e ?minus|plus digit+"),
                    Rule::seq("integer", "zero|digit1-9-seq"),
                    Rule::seq("frac", "decimal-point digit+"),
                )
                ->withRootSequence("?minus integer ?frac ?exp")
                ->setNodeType(NodeType::Raw)
                ->closeWith(Rule::taggedWith("_number_part"), true, false),
        );

        $grammar->setRootRegion($jsonText);

        return $grammar;
    }

    private function addNodeTypeSetupForRules(NodeType $nodeType): AddRuleMiddleware
    {
        return AddRuleMiddleware::fromCallable(
            static function (Rule $rule) use ($nodeType): Rule {
                $rule->setNodeType($nodeType);
                return $rule;
            }
        );
    }

    private function addTriviaMiddleware(): AddRuleMiddleware
    {
        return AddRuleMiddleware::fromCallable(
            static function (Rule $rule): Rule {
                if (!$rule->definition instanceof SequenceRule) {
                    return $rule;
                }

                $extender = new SequenceExtender();
                $extender
                    ->when(static fn(NestedSequence|SequenceNode $node, int $index, array $nodes): bool => $index < count($nodes) - 1)
                    ->addNext('ws*');

                $rule->definition = $extender->extend($rule->definition);

                return $rule;
            },
            10
        );
    }
}
