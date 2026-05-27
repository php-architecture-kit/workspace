<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Json;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\GrammarOrigin;
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
                    Rule::token("comma", ",", type: NodeType::Structure),
                )
                ->withRootSequence("beginArray -* ?(value[item] (-* comma -* value[item])*)/g -* endArray")
                ->closeWith(
                    Rule::token("endArray", "]", type: NodeType::Structure),
                )
                ->addTag("value"),
            Rule::token("beginObject", "{", type: NodeType::Structure)
                ->startRegion('object')
                ->enableInheritanceFromGlobal()
                ->add(
                    Rule::token("colon", ":", type: NodeType::Structure),
                    Rule::token("comma", ",", type: NodeType::Structure),
                    Rule::seq("member", "string[identifier] -* colon -* value"),
                )
                ->withRootSequence("beginObject -* ?(member (-* comma -* member)*)/g -* endObject")
                ->closeWith(
                    Rule::token("endObject", "}", type: NodeType::Structure),
                )
                ->addTag("value"),
            Rule::choice("primitive", ["false", "null", "true", "number", "string"], tags: ["value"]),
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
                    Rule::token("decimalPoint", ".", tags: ["_number_part"]),
                    Rule::token("plus", "+", tags: ["_number_part"]),
                    Rule::token("minus", "-", tags: ["_number_part"]),
                    Rule::token("zero", "0", tags: ["_number_part"]),
                    Rule::expr("digit19", "[1-9]", tags: ["_number_part"]),
                    Rule::expr("e", "[eE]", tags: ["_number_part"]),
                    Rule::seq("digit", "zero|digit19", type: NodeType::Raw),
                    Rule::seq("digit19Seq", "digit19 digit*", type: NodeType::Raw),
                    Rule::seq("exp", "e ?minus|plus digit+", type: NodeType::Raw),
                    Rule::seq("integer", "zero|digit19Seq", type: NodeType::Raw),
                    Rule::seq("frac", "decimalPoint digit+", type: NodeType::Raw),
                )
                ->withRootSequence("?minus integer ?frac ?exp")
                ->setNodeType(NodeType::Raw)
                ->closeWith(Rule::taggedWith("_number_part"), true, false),
        );

        $grammar->setRootRegion($jsonText);

        $grammar->stampOrigin(new GrammarOrigin(self::FORMAT, self::VARIANT));

        return $grammar;
    }
}
