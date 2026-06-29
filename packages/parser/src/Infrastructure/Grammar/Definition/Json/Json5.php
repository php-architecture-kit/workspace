<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Json;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\GrammarOrigin;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeType;

class Json5 extends JsonC
{
    public const FORMAT = "json";
    public const VARIANT = "5";

    public function grammar(): Grammar
    {
        parent::grammar();
        $regions = $this->grammar->getAllRegions();

        $this->grammar->global->add(
            // string
            Rule::token("singleQuote", "'", type: NodeType::Structure)
                ->startRegion("singleQuotedString", true)
                ->add(
                    Rule::expr("escapeHex", "\\\\x[0-9a-fA-F]{2}")->priority(2),
                    Rule::expr("escapeUnicode", "\\\\u[0-9a-fA-F]{4}")->priority(2),
                    Rule::expr("escapeChar", "\\\\[bfnrtv0\\\\\"/']")->priority(1),
                    Rule::expr("lineContinuation", '\\\\(\n|\r\n?|\x{2028}|\x{2029})')->priority(1),
                    Rule::expr("unescaped", "[^\\x00-\\x1F\\x27\\x5C]+"),
                )
                ->setNodeType(NodeType::Raw)
                ->closeWith(Rule::token("singleQuote", "'", type: NodeType::Structure))
                ->addTag("string"),

            // identifier variant
            Rule::expr("nonQuotedIdentifier", "[\p{L}_\$][\p{L}\p{N}_\$]*", type: NodeType::Raw),

            // literals
            Rule::keyword("NaN", true, "nan")
                ->priority(1),
            Rule::choice(
                "infinity",
                [
                    Rule::keyword("-Infinity", true, "negativeInfinity")->priority(1),
                    Rule::keyword("Infinity", true, "StandardInfinity")->priority(1),
                    Rule::keyword("+Infinity", true, "positiveInfinity")->priority(1)
                ],
                type: NodeType::Raw
            ),

            Rule::choice(
                "primitive",
                ["false", "null", "true", "infinity", "nan", "number", "string"],
                tags: ["value"],
                attributeTags: ['r']
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
            "?minus|plus[operator] (hexInteger|integer[integer] ?frac)|(leadingDotNumber) ?exp",
        );

        $regions['object']->add(
            Rule::seq("member", "nonQuotedIdentifier|string[identifier]/r -* colon -* value"),
        );

        $regions['object']
            ->withRootSequence("beginObject -t* ?(-l* member/c (-* comma -t* -l* member/c)* ?(-* comma[trailingComma]) -t*)[members]/g -l* endObject");

        $regions['array']
            ->withRootSequence("beginArray -t* ?(-l* value[item]/c (-* comma -t* -l* value[item]/c)* ?(-* comma[trailingComma]) -t*)[items]/g -l* endArray");

        $this->grammar->stampOrigin(
            new GrammarOrigin(self::FORMAT, self::VARIANT),
            forceRegions: ['number', 'object', 'array'],
        );

        return $this->grammar;
    }
}
