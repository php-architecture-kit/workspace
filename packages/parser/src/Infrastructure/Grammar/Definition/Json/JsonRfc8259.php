<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Json;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Definition;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Middleware\AddRuleMiddleware;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Region;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Technical\Whitespace;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeType;

class JsonRfc8259 extends Whitespace
{
    public const FORMAT = "json";
    public const VARIANT = "rfc8259";

    public function grammar(): Grammar
    {
        $grammar = parent::grammar();
        $jsonText = (new Region("json"))
            ->setInheritanceFromGlobal()
            ->withRootSequence("-* value -*");

        $grammar->global->add(
            $jsonText,
            Rule::token("beginArray", "[", type: NodeType::Structure)
                ->startRegion('array')
                ->enableInheritanceFromGlobal()
                ->add(
                    Rule::token("valueSeparator", ",", type: NodeType::Structure),
                    Rule::seq("itemContinuation", "-* valueSeparator -* value[item]"),
                    Rule::seq("items", "value[item] itemContinuation*"),
                )
                ->withRootSequence("beginArray -* ?items -* endArray")
                ->closeWith(
                    Rule::token("endArray", "]", type: NodeType::Structure),
                )
                ->addTag("value")
                ->asAstNode(
                    'Array',
                    Definition::child('Item', 'item', optional: true),
                ),
            Rule::token("beginObject", "{", type: NodeType::Structure)
                ->startRegion('object')
                ->enableInheritanceFromGlobal()
                ->add(
                    Rule::token("nameSeparator", ":", type: NodeType::Structure),
                    Rule::token("valueSeparator", ",", type: NodeType::Structure),
                    Rule::seq("member", "string[identifier] -* nameSeparator -* value")
                        ->asAstNode('Member'),
                    Rule::seq("memberContinuation", "-* valueSeparator -* member"),
                    Rule::seq("members", "member memberContinuation*"),
                )
                ->withRootSequence("beginObject -* ?members -* endObject")
                ->closeWith(
                    Rule::token("endObject", "}", type: NodeType::Structure),
                )
                ->addTag("value")
                ->asAstNode(
                    'Object',
                    Definition::child('Member', 'member', optional: true),
                ),
            Rule::choice("primitive", ["false", "null", "true", "number", "string"], tags: ["value"])
                ->asAstNode('Primitive'),
            Rule::keyword("null"),
            Rule::keyword("false"),
            Rule::keyword("true"),

            // string
            Rule::token("doubleQuote", "\"", type: NodeType::Structure)
                ->startRegion("string", true)
                ->add(
                    Rule::expr("escapeChar", "\\\\[bfnrt\\\\\\\"]")->priority(1),
                    Rule::expr("unescaped", "[^\\x00-\\x1F\\x22\\x5C]+"),
                    Rule::expr("escapeUnicode", "\\\\u[0-9a-fA-F]{4}"),
                )
                ->setNodeType(NodeType::Raw)
                ->closeWith(Rule::token("doubleQuote", "\"", type: NodeType::Structure)),

            // number
            Rule::token("decimalPoint", ".", tags: ["_number_part"]),
            Rule::token("plus", "+", tags: ["_number_part"]),
            Rule::token("minus", "-", tags: ["_number_part"]),
            Rule::token("zero", "0", tags: ["_number_part"]),
            Rule::expr("digit19", "[1-9]", tags: ["_number_part"]),
            Rule::expr("e", "[eE]", tags: ["_number_part"]),
            Rule::taggedWith("_number_part")
                ->startRegion("number", true)
                ->add(
                    $this->addNodeTypeSetupForRules(NodeType::Raw),
                    Rule::token("decimalPoint", ".", tags: ["_number_part"]),
                    Rule::token("plus", "+", tags: ["_number_part"]),
                    Rule::token("minus", "-", tags: ["_number_part"]),
                    Rule::token("zero", "0", tags: ["_number_part"]),
                    Rule::expr("digit19", "[1-9]", tags: ["_number_part"]),
                    Rule::expr("e", "[eE]", tags: ["_number_part"]),
                    Rule::seq("digit", "zero|digit19"),
                    Rule::seq("digit19Seq", "digit19 digit*"),
                    Rule::seq("exp", "e ?minus|plus digit+"),
                    Rule::seq("integer", "zero|digit19Seq"),
                    Rule::seq("frac", "decimalPoint digit+"),
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
            },
        );
    }
}
