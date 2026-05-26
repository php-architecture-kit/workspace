<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Json;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeType;

class Json5 extends JsonC
{
    public const FORMAT = "json";
    public const VARIANT = "5";

    public function grammar(): Grammar
    {
        $grammar = parent::grammar();
        $regions = $grammar->getAllRegions();

        $grammar->global->add(
            Rule::token("singleQuote", "'", type: NodeType::Structure)
                ->startRegion("singleQuotedString", true)
                ->add(
                    Rule::expr("escapeHex", "\\\\x[0-9a-fA-F]{2}")->priority(2),
                    Rule::expr("escapeChar", "\\\\[bfnrtv0\\\\\"/']")->priority(1),
                    Rule::expr("lineContinuation", "\\\\\n")->priority(1),
                    Rule::expr("unescaped", "[^\\x00-\\x1F\\x27\\x5C]+"),
                )
                ->setNodeType(NodeType::Raw)
                ->closeWith(Rule::token("singleQuote", "'", type: NodeType::Structure)),
            Rule::expr("identifierKey", "[a-zA-Z_\$][a-zA-Z0-9_\$]*", type: NodeType::Raw),
            Rule::expr("signedInfinity", "[+\\-]?Infinity", caseSensitive: true)
                ->priority(1)
                ->addTag("value"),
            Rule::keyword(keyword: "NaN", caseSensitive: true, name: "nan")
                ->priority(1)
                ->addTag("value"),
            Rule::choice(
                "primitive",
                ["false", "null", "true", "signedInfinity", "nan", "number", "string", "singleQuotedString"],
                tags: ["value"],
            ),
        );

        $regions['number']->add(
            Rule::seq("frac", "decimalPoint digit*", type: NodeType::Raw),
            Rule::expr("hexX", "[xX]", tags: ["_number_part"]),
            Rule::expr("hexLetter", "[a-fA-F]", tags: ["_number_part"]),
            Rule::seq("hexDigit", "zero|digit19|hexLetter", type: NodeType::Raw),
            Rule::seq("hexInteger", "zero hexX hexDigit+", type: NodeType::Raw),
            Rule::seq("leadingDotNumber", "decimalPoint digit+", type: NodeType::Raw),
        );
        $regions['number']->withRootSequence(
            "?minus|plus (hexInteger | leadingDotNumber | integer ?frac ?exp)",
        );

        $regions['object']->add(
            Rule::choice("key", [
                "string",
                "singleQuotedString",
                "identifierKey",
                "null",
                "true",
                "false",
                "signedInfinity",
                "nan"
            ]),
            Rule::seq("member", "key[identifier] -* colon -* value"),
        );
        $regions['object']->withRootSequence(
            "beginObject -* ?(member (-* comma -* member)* -* ?comma)/g -* endObject",
        );

        $regions['array']->withRootSequence(
            "beginArray -* ?(value[item] (-* comma -* value[item])* -* ?comma)/g -* endArray",
        );

        return $grammar;
    }
}
