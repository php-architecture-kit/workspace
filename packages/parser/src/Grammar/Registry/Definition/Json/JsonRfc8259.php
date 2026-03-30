<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Registry\Definition\Json;

use PhpArchitecture\Parser\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Grammar\Registry\Definition\Technical\Whitespace;

class JsonRfc8259 extends Whitespace
{
    public const FORMAT = "json";
    public const VARIANT = "rfc8259";

    public function grammar(): Grammar
    {
        $grammar = parent::grammar();

        $grammar->global->add(
            Rule::token("begin-array", "[")
                ->startRegion('array')
                ->enableInheritanceFromGlobal()
                ->add(
                    Rule::token("value-separator", ","),
                )
                ->closeWith(
                    Rule::token("end-array", "]"),
                )
                ->addTag("value"),
            Rule::token("begin-object", "{")
                ->startRegion('object')
                ->enableInheritanceFromGlobal()
                ->add(
                    Rule::token("name-separator", ":"),
                    Rule::token("value-separator", ","),
                )
                ->closeWith(
                    Rule::token("end-object", "}"),
                )
                ->addTag("value"),
            Rule::token("double-quote", "\"")
                ->startRegion("string", true)
                ->add(
                    Rule::expr("escape-char", "\\\\[bfnrt\\\\\\\"]")->priority(1),
                    Rule::expr("unescaped", "[^\\x00-\\x1F\\x22\\x5C]+"),
                    Rule::expr("escape-unicode", "\\\\u[0-9a-fA-F]{4}"),
                )
                ->closeWith(Rule::token("double-quote", "\""))
                ->addTag("value"),
            Rule::keyword("null", tags: ["value"]),
            Rule::keyword("false", tags: ["value"]),
            Rule::keyword("true", tags: ["value"]),
            Rule::token("decimal-point", ".", tags: ["_number_part"]),
            Rule::token("plus", "+", tags: ["_number_part"]),
            Rule::token("minus", "-", tags: ["_number_part"]),
            Rule::token("zero", "0", tags: ["_number_part"]),
            Rule::expr("digit1-9", "[1-9]", tags: ["_number_part"]),
            Rule::expr("e", "[eE]", tags: ["_number_part"]),
            Rule::taggedWith("_number_part")
                ->startRegion("number", true)
                ->add(
                    Rule::token("decimal-point", ".", tags: ["_number_part"]),
                    Rule::token("plus", "+", tags: ["_number_part"]),
                    Rule::token("minus", "-", tags: ["_number_part"]),
                    Rule::token("zero", "0", tags: ["_number_part"]),
                    Rule::expr("digit1-9", "[1-9]", tags: ["_number_part"]),
                    Rule::expr("e", "[eE]", tags: ["_number_part"]),
                )
                ->closeWith(Rule::taggedWith("_number_part"), true, false)
                ->addTag("value"),
        );

        $grammar->setRootRegion($grammar->global);

        return $grammar;
    }
}
