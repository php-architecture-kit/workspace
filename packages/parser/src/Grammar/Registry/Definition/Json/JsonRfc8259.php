<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Registry\Definition\Json;

use PhpArchitecture\Parser\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Grammar\Definition\Middleware\AddRuleMiddleware;
use PhpArchitecture\Parser\Grammar\Definition\Model\Sequence\SequenceRule;
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
                    Rule::seq("items", "value (value-separator value)*")
                )
                ->withRootSequence("begin-array items end-array")
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
                    Rule::seq("member", "string[identifier] name-separator value"),
                    Rule::seq("members", "member (value-separator member)*")
                )
                ->withRootSequence("begin-object members end-object")
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
                    Rule::seq("digit", "zero|digit1-9"),
                    Rule::seq("digit1-9-seq", "digit1-9 digit*"),
                    Rule::seq("exp", "e ?minus|plus digit+"),
                    Rule::seq("integer", "zero|digit1-9-seq"),
                    Rule::seq("frac", "decimal-point digit+"),
                )
                ->withRootSequence("?minus integer ?frac ?exp")
                ->closeWith(Rule::taggedWith("_number_part"), true, false)
                ->addTag("value"),

            Rule::keyword("null", tags: ["value"]),
            Rule::keyword("false", tags: ["value"]),
            Rule::keyword("true", tags: ["value"]),
        );

        $grammar->setRootRegion($grammar->global);

        return $grammar;
    }

    private function addTriviaMiddleware(): AddRuleMiddleware
    {
        return AddRuleMiddleware::fromCallable(
            static function (Rule $rule): Rule {
                if (!$rule->definition instanceof SequenceRule) {
                    return $rule;
                }

                // TODO

                return $rule;
            },
            10
        );
    }
}
